<?php

namespace Drupal\commerce_ups;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Entity\ShippingMethodInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\physical\LengthUnit;
use Drupal\physical\WeightUnit;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides a replacement of the UPS SDK.
 */
class UPSSdk implements UPSSdkInterface {

  /**
   * The client.
   */
  protected ClientInterface $client;

  /**
   * The configuration.
   */
  protected array $config = [];

  /**
   * List of service names.
   */
  protected array $serviceNames = [
    '01' => 'UPS Next Day Air',
    '02' => 'UPS 2nd Day Air',
    '03' => 'UPS Ground',
    '07' => 'UPS Worldwide Express',
    '08' => 'UPS Worldwide Expedited',
    '11' => 'UPS Standard',
    '12' => 'UPS 3 Day Select',
    '13' => 'UPS Next Day Air Saver',
    '14' => 'UPS Next Day Air Early',
    '54' => 'UPS Worldwide Express Plus',
    '71' => 'UPS Worldwide Express Freight Midday',
    '82' => 'UPS Today Standard',
    '83' => 'UPS Today Dedicated Courrier',
    '85' => 'UPS Today Express',
    '86' => 'UPS Today Express Saver',
    '59' => 'UPS Second Day Air AM',
    '65' => 'UPS Saver',
    '70' => 'UPS Access Point Economy',
    '74' => 'UPS Express 12:00',
    '93' => 'UPS Sure Post',
    '96' => 'UPS Worldwide Express Freight',
  ];

  /**
   * The shipment.
   */
  protected ?ShipmentInterface $shipment;

  /**
   * The shipping method.
   */
  protected ?ShippingMethodInterface $shippingMethod;

  /**
   * Whether rate request should be negotiated.
   */
  protected bool $isNegotiatedRates = FALSE;

  /**
   * Whether we should write logs on request.
   */
  protected bool $logRequest = FALSE;

  /**
   * Whether we should write logs on response.
   */
  protected bool $logResponse = FALSE;

  /**
   * Constructs a new UPSSdk object.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    protected CacheBackendInterface $cacheBackend,
    protected LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function setClient(ClientInterface $client): void {
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $config): void {
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessToken(): ResponseInterface {
    return $this->client->post(
      self::UPS_ACCESS_TOKEN_URL,
      [
        'auth' => [$this->config['client_id'], $this->config['secret']],
        'form_params' => [
          'grant_type' => 'client_credentials',
        ],
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setShipment(ShipmentInterface $shipment): void {
    $this->shipment = $shipment;
  }

  /**
   * {@inheritdoc}
   */
  public function setShippingMethod(ShippingMethodInterface $shipping_method): void {
    $this->shippingMethod = $shipping_method;
  }

  /**
   * {@inheritdoc}
   */
  public function setNegotiatedRates(bool $value): void {
    $this->isNegotiatedRates = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLogProcess(bool $log_request = FALSE, bool $log_response = FALSE): void {
    $this->logRequest = $log_request;
    $this->logResponse = $log_response;
  }

  /**
   * {@inheritdoc}
   */
  public function getServiceName(string $service): string {
    return $this->serviceNames[$service] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getShipmentShopRates(): array {
    if (!$this->shipment) {
      throw new \InvalidArgumentException('Shipment not provided.');
    }
    $params = $this->prepareRatesRequest();
    $cid = 'rate-request:' . hash('sha256', serialize($params));
    $rate_request_cache = $this->cacheBackend->get($cid);
    if (!empty($rate_request_cache)) {
      return $rate_request_cache->data;
    }

    if ($this->logRequest) {
      $this->log('Sending UPS Rate request with params', $params);
    }
    $response = $this->client->post(self::UPS_API_SHOP_RATE_URL, ['body' => Json::encode($params)]);
    $rates = Json::decode($response->getBody());
    if ($this->logResponse) {
      $this->log('Received UPS rate response', $rates);
    }
    if (!empty($rates['RateResponse']['RatedShipment'])) {
      $expires = time() + self::RATE_CACHE_DURATION;
      $this->cacheBackend->set($cid, $rates, $expires);
      return $rates;
    }

    return [];
  }

  /**
   * Prepares rate request params.
   *
   * @param string $request_option
   *   The request options.
   */
  protected function prepareRatesRequest(string $request_option = 'Shop'): array {
    if (!$this->shippingMethod) {
      return [];
    }

    return [
      'RateRequest' => [
        'Request' => [
          'RequestOption' => $request_option,
        ],
        'PickupType' => [
          'Code' => '01',
        ],
        'Shipment' => $this->getShipment(),
      ],
    ];
  }

  /**
   * Returns information about shipment for UPS API.
   *
   * @return array
   *   Shipment data.
   */
  protected function getShipment(): array {
    $shipment = [];
    /** @var \CommerceGuys\Addressing\AddressInterface $address_from */
    $address_from = $this->shipment->getOrder()?->getStore()?->getAddress();

    /** @var \CommerceGuys\Addressing\AddressInterface $address_to */
    $address_to = $this->shipment->getShippingProfile()->get('address')->first();
    $addresses = [
      'ShipTo' => $address_to,
      'Shipper' => $address_from,
      'ShipFrom' => $address_from,
    ];
    foreach ($addresses as $direction => $address) {
      $shipment[$direction]['Address'] = [
        'AddressLine' => [
          $address->getAddressLine1(),
          $address->getAddressLine2(),
        ],
        'City' => $address->getLocality(),
        'StateProvinceCode' => $address->getAdministrativeArea(),
        'PostalCode' => $address->getPostalCode(),
        'CountryCode' => $address->getCountryCode(),
      ];
      if (!empty($address->getOrganization())) {
        $shipment[$direction]['AttentionName'] = $address->getOrganization();
      }
    }

    // Add "ShipperNumber" as it required fo negotiated rates.
    $shipment['Shipper']['ShipperNumber'] = $this->config['account_number'];

    $shipment['Package'] = $this->getPackages($address_from->getCountryCode());

    if ($this->isNegotiatedRates) {
      $shipment['ShipmentRatingOptions'] = [
        'NegotiatedRatesIndicator' => '1',
        'RateChartIndicator' => '0',
      ];
    }

    return $shipment;
  }

  /**
   * Returns the list of packages for UPS API request.
   *
   * The list of properties that can be used in every package can be
   * checked at the link below.
   *
   * @param string $country_code
   *   The country code for correct measurement.
   *
   * @return array
   *   An array of packages in the shipment.
   *
   * @see https://developer.ups.com/api/reference?loc=en_US#operation/Rate!path=RateRequest/Shipment/Package&t=request
   */
  protected function getPackages(string $country_code): array {
    $packages = [];
    // Set package type.
    $package_type = $this->shipment->getPackageType()
      ?? $this->shippingMethod->getPlugin()->getDefaultPackageType();
    $remote_id = $package_type->getRemoteId();
    $type_code = !empty($remote_id) && $remote_id !== 'custom' ? $remote_id : '00';

    // Set package weight.
    $weight_unit = $country_code === 'US' ? WeightUnit::POUND : WeightUnit::KILOGRAM;
    $weight = $this->shipment->getWeight()?->convert($weight_unit);
    $weight_unit = match ($weight->getUnit()) {
      'lb' => 'LBS',
      'kg' => 'KGS',
    };

    // UPS API require weight value to be exact 6 symbols long.
    $weight_number = (string) ceil($weight->getNumber());
    $weight_number_length = strlen($weight_number);
    if ($weight_number_length < 6) {
      for ($i = 0; $i < (6 - $weight_number_length); $i++) {
        $weight_number = '0' . $weight_number;
      }
    }

    // Set package dimensions.
    $length_unit = $country_code === 'US' ? LengthUnit::INCH : LengthUnit::CENTIMETER;
    $length = ceil($package_type->getLength()
      ->convert($length_unit)
      ->getNumber());
    $height = ceil($package_type->getHeight()
      ->convert($length_unit)
      ->getNumber());
    $width = ceil($package_type->getWidth()
      ->convert($length_unit)
      ->getNumber());

    $packages[] = [
      'PackagingType' => [
        'Code' => $type_code,
      ],
      'PackageWeight' => [
        'UnitOfMeasurement' => [
          'Code' => $weight_unit,
          'Description' => $weight->getUnit(),
        ],
        'Weight' => $weight_number,
      ],
      'Dimensions' => [
        'UnitOfMeasurement' => [
          'Code' => strtoupper($length_unit),
          'Description' => $length_unit,
        ],
        'Length' => number_format($length, 2, '.'),
        'Height' => number_format($height, 2, '.'),
        'Width' => number_format($width, 2, '.'),
      ],
    ];

    return $packages;
  }

  /**
   * Logs information from UPS API requests.
   *
   * @param string $message
   *   The message to log.
   * @param mixed|null $data
   *   Message data.
   */
  protected function log(string $message, $data = NULL): void {
    if ($data) {
      $this->logger->info('@message <br /><pre>@data</pre>', [
        '@message' => $message,
        '@data' => json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT),
      ]);
    }
    else {
      $this->logger->info($message);
    }
  }

}
