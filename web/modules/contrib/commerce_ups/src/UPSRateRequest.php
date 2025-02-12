<?php

namespace Drupal\commerce_ups;

use Drupal\commerce_price\Price;
use Drupal\commerce_price\RounderInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Entity\ShippingMethodInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use Psr\Log\LoggerInterface;

/**
 * Represents the UPS rate request.
 */
class UPSRateRequest implements UPSRateRequestInterface {

  /**
   * The configuration array from a CommerceShippingMethod.
   */
  protected array $configuration;

  /**
   * Constructs a new UPSRateRequest object.
   *
   * @param \Drupal\commerce_ups\UPSSdkFactoryInterface $upsSdkFactory
   *   The UPS SDK factory.
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   The price rounder.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    protected UPSSdkFactoryInterface $upsSdkFactory,
    protected RounderInterface $rounder,
    protected LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function setConfig(array $configuration): void {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getRates(ShipmentInterface $commerce_shipment, ShippingMethodInterface $shipping_method): array {
    $rates = [];

    try {
      $sdk = $this->upsSdkFactory->get($this->configuration['api_information']);
      $sdk->setShipment($commerce_shipment);
      $sdk->setShippingMethod($shipping_method);
      // Get negotiated rates, if enabled.
      $sdk->setNegotiatedRates($this->getRateType());
      // Enable logging.
      $sdk->setLogProcess(
        !empty($this->configuration['options']['log']['request']),
        !empty($this->configuration['options']['log']['response'])
      );
      $ups_rates = $sdk->getShipmentShopRates();
      if (empty($ups_rates)) {
        return [];
      }
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
      $ups_rates = [];
    }

    if (!empty($ups_rates['RateResponse']['RatedShipment'])) {
      foreach ($ups_rates['RateResponse']['RatedShipment'] as $ups_rate) {
        $service_code = $ups_rate['Service']['Code'];

        // Only add the rate if this service is enabled.
        if (!in_array($service_code, $this->configuration['services'])) {
          continue;
        }

        // Use negotiated rates if they were returned.
        if ($this->getRateType() && !empty($ups_rate["NegotiatedRateCharges"]["TotalCharge"]["MonetaryValue"])) {
          $cost = $ups_rate["NegotiatedRateCharges"]["TotalCharge"]["MonetaryValue"];
          $currency = $ups_rate["NegotiatedRateCharges"]["TotalCharge"]["CurrencyCode"];
        }
        // Otherwise, use the default rates.
        else {
          $cost = $ups_rate['TotalCharges']['MonetaryValue'];
          $currency = $ups_rate['TotalCharges']['CurrencyCode'];
        }

        $price = new Price((string) $cost, $currency);
        $service_name = $sdk->getServiceName($service_code);

        $multiplier = (!empty($this->configuration['rate_options']['rate_multiplier']))
          ? $this->configuration['rate_options']['rate_multiplier']
          : 1.0;
        if ($multiplier != 1) {
          $price = $price->multiply((string) $multiplier);
        }

        $round = !empty($this->configuration['options']['round'])
          ? $this->configuration['options']['round']
          : PHP_ROUND_HALF_UP;
        $price = $this->rounder->round($price, $round);
        $rates[] = new ShippingRate([
          'shipping_method_id' => $shipping_method->id(),
          'service' => new ShippingService($service_code, $service_name),
          'amount' => $price,
        ]);
      }
    }
    return $rates;
  }

  /**
   * Gets the rate type: whether we will use negotiated rates or standard rates.
   *
   * @return bool
   *   Returns true if negotiated rates should be requested.
   */
  public function getRateType(): bool {
    return (bool) $this->configuration['rate_options']['rate_type'];
  }

}
