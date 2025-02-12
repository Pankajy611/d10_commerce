<?php

namespace Drupal\Tests\commerce_ups\Unit;

use Drupal\commerce_price\Entity\CurrencyInterface;
use Drupal\commerce_price\Rounder;
use Drupal\commerce_shipping\Entity\ShippingMethodInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_ups\UPSRateRequest;
use Drupal\commerce_ups\UPSRateRequestInterface;
use Drupal\commerce_ups\UPSSdk;
use Drupal\commerce_ups\UPSSdkFactory;
use Drupal\commerce_ups\UPSSdkFactoryInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\physical\LengthUnit;
use Drupal\physical\WeightUnit;
use GuzzleHttp\HandlerStack;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Tests the UPS rate request.
 *
 * @coversDefaultClass \Drupal\commerce_ups\UPSRateRequest
 * @group commerce_ups
 */
class UPSRateRequestTest extends UPSUnitTestBase {

  /**
   * A UPS rate request object.
   */
  protected UPSRateRequestInterface $rateRequest;

  /**
   * The rounder.
   */
  protected Rounder $rounder;

  /**
   * The cache backend service.
   */
  protected CacheBackendInterface|ObjectProphecy $cacheBackend;

  /**
   * The token key.
   */
  protected string $tokenKey;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    global $_UPS_ACCESS_TOKEN_;

    $logger_factory = $this->prophesize(LoggerChannelFactoryInterface::class);
    $logger = $this->prophesize(LoggerChannelInterface::class);
    $logger_factory->get('commerce_ups')->willReturn($logger->reveal());

    $usd_currency = $this->prophesize(CurrencyInterface::class);
    $usd_currency->id()->willReturn('USD');
    $usd_currency->getFractionDigits()->willReturn('2');

    $eur_currency = $this->prophesize(CurrencyInterface::class);
    $eur_currency->id()->willReturn('EUR');
    $eur_currency->getFractionDigits()->willReturn('2');

    $storage = $this->prophesize(EntityStorageInterface::class);
    $storage->load('USD')->willReturn($usd_currency->reveal());
    $storage->load('EUR')->willReturn($eur_currency->reveal());

    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getStorage('commerce_currency')
      ->willReturn($storage->reveal());

    $this->rounder = new Rounder($entity_type_manager->reveal());
    $this->cacheBackend = $this->prophesize(CacheBackendInterface::class);

    $state = $this->createMock(StateInterface::class);

    $ups_sdk = new UPSSdk($this->cacheBackend->reveal(), $logger->reveal());

    $sdk_factory = new UPSSdkFactory(
      new ClientFactory(HandlerStack::create()),
      HandlerStack::create(),
      $state,
      $ups_sdk
    );

    // Get access token for requests.
    $config = $this->configuration['api_information'];
    $token_key = UPSSdkFactoryInterface::TOKEN_KEY . '.' . md5($config['client_id'] . $config['secret']);
    if (empty($_UPS_ACCESS_TOKEN_)) {
      $client = $sdk_factory->get($config);
      $response = $client->getAccessToken();
      if ($response->getStatusCode() == '200') {
        $token_data = Json::decode($response->getBody()->getContents());
        $_UPS_ACCESS_TOKEN_ = [
          'token' => $token_data['access_token'],
          'type' => $token_data['token_type'],
          'expires' => time() + (int) $token_data['expires_in'],
        ];
      }
    }

    if (!empty($_UPS_ACCESS_TOKEN_)) {
      $state->method('get')
        ->with($token_key, FALSE)
        ->willReturn($_UPS_ACCESS_TOKEN_);
    }

    $this->rateRequest = new UPSRateRequest(
      $sdk_factory,
      $this->rounder,
      $logger->reveal()
    );
    $this->rateRequest->setConfig($this->configuration);
  }

  /**
   * Test useIntegrationMode().
   *
   * @covers ::useIntegrationMode
   */
  public function testIntegrationMode() {
    $mode = $this->configuration['api_information']['mode'] !== 'live';

    $this->assertTrue($mode);
  }

  /**
   * Test getRateType().
   *
   * @covers ::getRateType
   */
  public function testRateType() {
    $type = $this->rateRequest->getRateType();

    $this->assertTrue($type);
  }

  /**
   * Test rate requests return valid rates.
   *
   * @param string $weight_unit
   *   Weight unit.
   * @param string $length_unit
   *   Length unit.
   * @param bool $send_from_usa
   *   Whether the shipment should be sent from USA.
   *
   * @covers ::getRates
   *
   * @dataProvider measurementUnitsDataProvider
   */
  public function testRateRequest(string $weight_unit, string $length_unit, bool $send_from_usa) {
    $shipment = $this->mockShipment($weight_unit, $length_unit, $send_from_usa);
    $shipping_method_plugin = $this->mockShippingMethod();
    $shipping_method_entity = $this->prophesize(ShippingMethodInterface::class);
    $shipping_method_entity->id()->willReturn('123456789');
    $shipping_method_entity->getPlugin()->willReturn($shipping_method_plugin);
    $rates = $this->rateRequest->getRates($shipment, $shipping_method_entity->reveal());

    // Make sure at least one rate was returned.
    $this->assertArrayHasKey(0, $rates);

    foreach ($rates as $rate) {
      assert($rate instanceof ShippingRate);
      $this->assertGreaterThan(0, $rate->getAmount()->getNumber());
      $this->assertGreaterThan(0, $rate->getOriginalAmount()->getNumber());
      $this->assertEquals($rate->getAmount()
        ->getCurrencyCode(), $send_from_usa ? 'USD' : 'EUR');
      $this->assertNotEmpty($rate->getService()->getLabel());
      $this->assertEquals('123456789', $rate->getShippingMethodId());
    }
  }

  /**
   * Data provider for testRateRequest()
   */
  public function measurementUnitsDataProvider() {
    $weight_units = [
      WeightUnit::GRAM,
      WeightUnit::KILOGRAM,
      WeightUnit::OUNCE,
      WeightUnit::POUND,
    ];
    $length_units = [
      LengthUnit::MILLIMETER,
      LengthUnit::CENTIMETER,
      LengthUnit::METER,
      LengthUnit::INCH,
      LengthUnit::FOOT,
    ];
    foreach ($weight_units as $weight_unit) {
      foreach ($length_units as $length_unit) {
        yield [$weight_unit, $length_unit, TRUE];
        yield [$weight_unit, $length_unit, FALSE];
      }
    }
  }

}
