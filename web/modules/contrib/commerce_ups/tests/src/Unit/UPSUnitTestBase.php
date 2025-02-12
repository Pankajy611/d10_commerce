<?php

namespace Drupal\Tests\commerce_ups\Unit;

use CommerceGuys\Addressing\Address;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\physical\Length;
use Drupal\physical\Weight;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Base class for UPS Unit tests.
 *
 * @package Drupal\Tests\commerce_ups\Unit
 */
abstract class UPSUnitTestBase extends UnitTestCase {

  /**
   * A shipping method configuration array.
   */
  protected array $configuration = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();
    $this->configuration = [
      'api_information' => [
        'account_number' => 'B17855',
        'client_id' => 'AlCOUGCAJ5ZoLRuJ2GOp40yAuwIRw9UoEc1qNaGPuDdOLlK3',
        'secret' => 'rx67V3tvaHkp30fiyGrIFo4W9UOH6Ky09prKkIUGTtM19QRECLjzToFVLDlLigPt',
        'mode' => 'test',
      ],
      'rate_options' => [
        'rate_type' => TRUE,
      ],
      'services' => [
        "01" => "01",
        "02" => "02",
        "03" => "03",
        "07" => "07",
        "08" => "08",
        "11" => "11",
        "12" => "12",
        "13" => "13",
        "14" => "14",
        "54" => "54",
        "59" => "59",
        "65" => "65",
        "70" => "70",
      ],
    ];
  }

  /**
   * Creates and returns a mock Drupal Commerce shipment entity.
   *
   * @param string $weight_unit
   *   Weight measurement unit.
   * @param string $length_unit
   *   Length measurement unit.
   * @param bool $send_form_usa
   *   Whether the shipment should be sent from the USA.
   */
  public function mockShipment(string $weight_unit = 'lb', string $length_unit = 'in', bool $send_form_usa = TRUE): ShipmentInterface {
    // Mock a Drupal Commerce Order and associated objects.
    $order = $this->prophesize(OrderInterface::class);
    $store = $this->prophesize(StoreInterface::class);

    // Mock the getAddress method to return a US address.
    if ($send_form_usa) {
      $store->getAddress()->willReturn(new Address('US', 'NC', 'Asheville', '', 28806, '', '1025 Brevard Rd'));
    }
    else {
      // Mock the address list to ship to Germany address.
      // To those who are wondering, this is where Drupal Europe 2018 took
      // place.
      $store->getAddress()->willReturn(new Address('DE', '', 'Darmstadt', '', 64283, '', 'Schlossgraben 1'));
    }
    $order->getStore()->willReturn($store->reveal());

    // Mock a Drupal Commerce shipment and associated objects.
    $shipment = $this->prophesize(ShipmentInterface::class);
    $profile = $this->prophesize(ProfileInterface::class);
    $address_list = $this->prophesize(FieldItemListInterface::class);

    // Mock the address list to return a US address.
    $address_list->first()->willReturn(new Address('US', 'NC', 'Asheville', '', 28806, '', '1025 Brevard Rd'));
    $profile->get('address')->willReturn($address_list->reveal());
    $shipment->getShippingProfile()->willReturn($profile->reveal());
    $shipment->getOrder()->willReturn($order->reveal());

    // Mock a package type including dimensions and remote id.
    $package_type = $this->prophesize(PackageTypeInterface::class);
    $package_type->getHeight()->willReturn((new Length(10, 'in'))->convert($length_unit));
    $package_type->getLength()->willReturn((new Length(10, 'in'))->convert($length_unit));
    $package_type->getWidth()->willReturn((new Length(3, 'in'))->convert($length_unit));
    $package_type->getRemoteId()->willReturn('00');

    // Mock the shipment weight and package type.
    $shipment->getWeight()->willReturn((new Weight(10, 'lb'))->convert($weight_unit));
    $shipment->getPackageType()->willReturn($package_type->reveal());

    // Return the mocked shipment object.
    return $shipment->reveal();
  }

  /**
   * Creates and returns a mock Drupal Commerce shipping method.
   */
  public function mockShippingMethod(): ShippingMethodInterface {
    $shipping_method = $this->prophesize(ShippingMethodInterface::class);
    $package_type = $this->prophesize(PackageTypeInterface::class);
    $package_type->getHeight()->willReturn(new Length(10, 'in'));
    $package_type->getLength()->willReturn(new Length(10, 'in'));
    $package_type->getWidth()->willReturn(new Length(3, 'in'));
    $package_type->getWeight()->willReturn(new Weight(10, 'lb'));
    $package_type->getRemoteId()->willReturn('custom');
    $shipping_method->getDefaultPackageType()->willReturn($package_type);

    return $shipping_method->reveal();
  }

}
