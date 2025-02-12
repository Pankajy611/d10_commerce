<?php

namespace Drupal\commerce_usps\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use USPS\Rate;

/**
 * Rate request event for USPS.
 */
class USPSRateRequestEvent extends EventBase {

  /**
   * RateRequestEvent constructor.
   *
   * @param \USPS\Rate $rateRequest
   *   The USPS rate request object.
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The Commerce Shipment entity.
   */
  public function __construct(protected Rate $rateRequest, protected ShipmentInterface $shipment) {}

  /**
   * Gets the rate request object.
   *
   * @return \USPS\Rate
   *   The rate request object.
   */
  public function getRateRequest(): Rate {
    return $this->rateRequest;
  }

  /**
   * Set the rate request.
   *
   * @param \USPS\Rate $rate_request
   *   The USPS rate object.
   */
  public function setRateRequest(Rate $rate_request) {
    $this->rateRequest = $rate_request;
  }

  /**
   * Gets the shipment.
   *
   * @return \Drupal\commerce_shipping\Entity\ShipmentInterface
   *   The shipment.
   */
  public function getShipment(): ShipmentInterface {
    return $this->shipment;
  }

}
