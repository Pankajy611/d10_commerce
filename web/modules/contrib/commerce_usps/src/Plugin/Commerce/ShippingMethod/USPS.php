<?php

namespace Drupal\commerce_usps\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_shipping\Entity\ShipmentInterface;

/**
 * Provides the USPS shipping method.
 *
 * The "_9xxx" ids represent various First-Class Mail services that
 * share the same service id of 0 from USPS api (sad!).
 * @see \Drupal\commerce_usps\USPSRateRequest::resolveRates().
 *
 * @CommerceShippingMethod(
 *  id = "usps",
 *  label = @Translation("USPS"),
 *  services = {
 *    "_0" = @translation("First-Class Mail Large Envelope"),
 *    "_1" = @translation("Priority Mail"),
 *    "_1058" = @translation("USPS Ground Advantage"),
 *    "_1096" = @translation("USPS Ground Advantage Cubic"),
 *    "_13" = @translation("Priority Mail Express 2-Day Flat Rate Envelope"),
 *    "_16" = @translation("Priority Mail Flat Rate Envelope"),
 *    "_17" = @translation("Priority Mail Medium Flat Rate Box"),
 *    "_2058" = @translation("USPS Ground Advantage Hold For Pickup"),
 *    "_2096" = @translation("USPS Ground Advantage Cubic Hold For Pickup"),
 *    "_22" = @translation("Priority Mail Large Flat Rate Box"),
 *    "_27" = @translation("Priority Mail Express 2-Day Flat Rate Envelope Hold For Pickup"),
 *    "_28" = @translation("Priority Mail Small Flat Rate Box"),
 *    "_29" = @translation("Priority Mail Padded Flat Rate Envelope"),
 *    "_3" = @translation("Priority Mail Express 2-Day Hold For Pickup"),
 *    "_30" = @translation("Priority Mail Express 2-Day Legal Flat Rate Envelope"),
 *    "_31" = @translation("Priority Mail Express 2-Day Legal Flat Rate Envelope Hold For Pickup"),
 *    "_33" = @translation("Priority Mail Hold For Pickup"),
 *    "_34" = @translation("Priority Mail Large Flat Rate Box Hold For Pickup"),
 *    "_35" = @translation("Priority Mail Medium Flat Rate Box Hold For Pickup"),
 *    "_36" = @translation("Priority Mail Small Flat Rate Box Hold For Pickup"),
 *    "_37" = @translation("Priority Mail Flat Rate Envelope Hold For Pickup"),
 *    "_38" = @translation("Priority Mail Gift Card Flat Rate Envelope"),
 *    "_39" = @translation("Priority Mail Gift Card Flat Rate Envelope Hold For Pickup"),
 *    "_40" = @translation("Priority Mail Window Flat Rate Envelope"),
 *    "_4001" = @translation("Priority Mail Express 2-Day HAZMAT"),
 *    "_4010" = @translation("Priority Mail HAZMAT"),
 *    "_4012" = @translation("Priority Mail Large Flat Rate Box HAZMAT"),
 *    "_4013" = @translation("Priority Mail Medium Flat Rate Box HAZMAT"),
 *    "_4014" = @translation("Priority Mail Small Flat Rate Box HAZMAT"),
 *    "_4058" = @translation("USPS Ground Advantage HAZMAT"),
 *    "_4096" = @translation("USPS Ground Advantage Cubic HAZMAT"),
 *    "_41" = @translation("Priority Mail Window Flat Rate Envelope Hold For Pickup"),
 *    "_42" = @translation("Priority Mail Small Flat Rate Envelope"),
 *    "_43" = @translation("Priority Mail Small Flat Rate Envelope Hold For Pickup"),
 *    "_44" = @translation("Priority Mail Legal Flat Rate Envelope"),
 *    "_45" = @translation("Priority Mail Legal Flat Rate Envelope Hold For Pickup"),
 *    "_46" = @translation("Priority Mail Padded Flat Rate Envelope Hold For Pickup"),
 *    "_6001" = @translation("Priority Mail Express 2-Day Parcel Locker"),
 *    "_6010" = @translation("Priority Mail Parcel Locker"),
 *    "_6012" = @translation("Priority Mail Large Flat Rate Box Parcel Locker"),
 *    "_6013" = @translation("Priority Mail Medium Flat Rate Box Parcel Locker"),
 *    "_6014" = @translation("Priority Mail Small Flat Rate Box Parcel Locker"),
 *    "_6058" = @translation("USPS Ground Advantage Parcel Locker"),
 *    "_6075" = @translation("Library Mail Parcel Parcel Locker"),
 *    "_6076" = @translation("Media Mail Parcel Parcel Locker"),
 *    "_6096" = @translation("USPS Ground Advantage Cubic Parcel Locker"),
 *    "_62" = @translation("Priority Mail Express 2-Day Padded Flat Rate Envelope"),
 *    "_63" = @translation("Priority Mail Express 2-Day Padded Flat Rate Envelope Hold For Pickup"),
 *  }
 * )
 */
class USPS extends USPSBase {

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    // Only attempt to collect rates if an address exists on the shipment.
    if ($shipment->getShippingProfile()->get('address')->isEmpty()) {
      return [];
    }

    // Only attempt to collect rates for US addresses.
    if ($shipment->getShippingProfile()->get('address')->country_code != 'US') {
      return [];
    }

    // Make sure a package type is set on the shipment.
    $this->setPackageType($shipment);

    return $this->uspsRateService->getRates($shipment, $this->parentEntity);
  }

}
