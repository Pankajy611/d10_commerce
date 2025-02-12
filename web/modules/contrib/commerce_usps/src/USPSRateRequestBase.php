<?php

namespace Drupal\commerce_usps;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Entity\ShippingMethodInterface;
use Drupal\commerce_usps\Event\USPSEvents;
use Drupal\commerce_usps\Event\USPSRateRequestEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use USPS\Rate;

/**
 * Class USPSRateRequest.
 *
 * @package Drupal\commerce_usps
 */
abstract class USPSRateRequestBase extends USPSRequest implements USPSRateRequestInterface {

  /**
   * The commerce shipment entity.
   *
   * @var \Drupal\commerce_shipping\Entity\ShipmentInterface
   */
  protected ShipmentInterface $commerceShipment;

  /**
   * The shipping method being rated.
   *
   * @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface
   */
  protected ShippingMethodInterface $shippingMethod;

  /**
   * The configuration array from a CommerceShippingMethod.
   *
   * @var array
   */
  protected array $configuration;

  /**
   * The USPS rate request API.
   *
   * @var \USPS\Rate
   */
  protected Rate $uspsRequest;

  /**
   * USPSRateRequest constructor.
   *
   * @param \Drupal\commerce_usps\USPSShipmentInterface $uspsShipment
   *   The USPS shipment object.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface
   */
  public function __construct(protected USPSShipmentInterface $uspsShipment, protected EventDispatcherInterface $eventDispatcher, protected LoggerInterface $logger) {}

  /**
   * {@inheritdoc}
   */
  public function setConfig(array $configuration) {
    parent::setConfig($configuration);
    // Set the configuration on the USPS Shipment service.
    $this->uspsShipment->setConfig($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getRates(ShipmentInterface $commerce_shipment, ShippingMethodInterface $shipping_method) {
    // Set the necessary info needed for the request.
    $this->setShipment($commerce_shipment);
    $this->setShippingMethod($shipping_method);

    // Build the rate request.
    $this->buildRate();

    // Allow others to alter the rate.
    $this->alterRate();

    // Fetch the rates.
    $this->logRequest();
    $this->uspsRequest->getRate(10);
    $this->logResponse();
    $response = $this->uspsRequest->getArrayResponse();

    return $this->resolveRates($response);
  }

  /**
   * Build the rate request.
   */
  public function buildRate() {
    $this->uspsRequest = new Rate(
      $this->configuration['api_information']['user_id']
    );
    $this->setMode();
  }

  /**
   * Allow rate to be altered.
   */
  public function alterRate() {
    // Allow other modules to alter the rate request before it's submitted.
    $rateRequestEvent = new USPSRateRequestEvent($this->uspsRequest, $this->commerceShipment);
    $this->eventDispatcher->dispatch($rateRequestEvent, USPSEvents::BEFORE_RATE_REQUEST);
  }

  /**
   * Set the commerce shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $commerce_shipment
   *   The commerce shipment entity.
   */
  public function setShipment(ShipmentInterface $commerce_shipment) {
    $this->commerceShipment = $commerce_shipment;
  }

  /**
   * Set the shipping method being rated.
   *
   * @param \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method
   *   The shipping method.
   */
  public function setShippingMethod(ShippingMethodInterface $shipping_method) {
    $this->shippingMethod = $shipping_method;
  }

  /**
   * Logs the request data.
   */
  protected function logRequest() {
    if (!empty($this->configuration['options']['log']['request'])) {
      $request = $this->uspsRequest->getPostData();
      $this->logger->info('@message', ['@message' => print_r($request, TRUE)]);
    }
  }

  /**
   * Logs the response data.
   */
  protected function logResponse() {
    if (!empty($this->configuration['options']['log']['response'])) {
      $this->logger->info('@message', ['@message' => print_r($this->uspsRequest->getResponse(), TRUE)]);
    }
  }

  /**
   * Set the mode to either test/live.
   */
  protected function setMode() {
    $this->uspsRequest->setTestMode($this->isTestMode());
  }

  /**
   * Get an array of USPS packages.
   *
   * @return array
   *   An array of USPS packages.
   */
  public function getPackages() {
    // @todo: Support multiple packages.
    return [$this->uspsShipment->getPackage($this->commerceShipment)];
  }

  /**
   * Utility function to clean the USPS service name.
   *
   * @param string $service
   *   The service id.
   *
   * @return string
   *   The cleaned up service id.
   */
  public function cleanServiceName($service) {
    // Remove the html encoded trademark markup since it's
    // not supported in radio labels.
    $service = str_replace('&lt;sup&gt;&#8482;&lt;/sup&gt;', '', $service);
    $service = str_replace('&lt;sup&gt;&#174;&lt;/sup&gt;', '', $service);
    return $service;
  }

}
