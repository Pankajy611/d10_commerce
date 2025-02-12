<?php

namespace Drupal\commerce_usps\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use USPS\RatePackage;

/**
 * Rate request event for USPS.
 */
class USPSShipmentEvent extends EventBase {

  /**
   * RateRequestEvent constructor.
   *
   * @param \USPS\RatePackage $uspsPackage
   *   The USPS rate package object.
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The Commerce Shipment entity.
   */
  public function __construct(protected RatePackage $uspsPackage, protected ShipmentInterface $shipment) {}

  /**
   * Get the rate package.
   *
   * @return \USPS\RatePackage
   *   The rate request object.
   */
  public function getPackage(): RatePackage {
    return $this->uspsPackage;
  }

  /**
   * Set the rate package.
   *
   * @param \USPS\RatePackage $usps_package
   *   The USPS package object.
   */
  public function setPackage(RatePackage $usps_package) {
    $this->uspsPackage = $usps_package;
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
