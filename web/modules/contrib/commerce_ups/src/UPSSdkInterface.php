<?php

namespace Drupal\commerce_ups;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Entity\ShippingMethodInterface;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides an interface of the UPS SDK.
 */
interface UPSSdkInterface {

  /**
   * Url for access token request.
   */
  const UPS_ACCESS_TOKEN_URL = '/security/v1/oauth/token';

  /**
   * Url for Shop rate request.
   */
  const UPS_API_SHOP_RATE_URL = '/api/rating/v1/Shop';

  /**
   * How long the rate request will be cached.
   */
  const RATE_CACHE_DURATION = 3600;

  /**
   * Sets client for UPS SDK instance.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The client.
   */
  public function setClient(ClientInterface $client): void;

  /**
   * Provides configuration for UPS SDK instance.
   *
   * @param array $config
   *   Instance configurations.
   */
  public function setConfiguration(array $config): void;

  /**
   * Gets an access token.
   */
  public function getAccessToken(): ResponseInterface;

  /**
   * Sets the shipment property.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   */
  public function setShipment(ShipmentInterface $shipment): void;

  /**
   * Sets the shipping method property.
   *
   * @param \Drupal\commerce_shipping\Entity\ShippingMethodInterface $shipping_method
   *   The shipping method.
   */
  public function setShippingMethod(ShippingMethodInterface $shipping_method): void;

  /**
   * Sets the negotiated property.
   *
   * @param bool $value
   *   Weather rate request should be negotiated.
   */
  public function setNegotiatedRates(bool $value): void;

  /**
   * Sets the log process property.
   *
   * @param bool $log_request
   *   Whether we should write logs on request.
   * @param bool $log_response
   *   Whether we should write logs on response.
   */
  public function setLogProcess(bool $log_request = FALSE, bool $log_response = FALSE): void;

  /**
   * Gets a list of rates from request.
   */
  public function getShipmentShopRates(): array;

  /**
   * Returns the service name.
   *
   * @param string $service
   *   Service code.
   */
  public function getServiceName(string $service): string;

}
