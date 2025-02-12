<?php

namespace Drupal\commerce_ups\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\SupportsTrackingInterface;
use Drupal\commerce_ups\UPSRateRequestInterface;
use Drupal\commerce_ups\UPSSdkFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the UPS shipping method.
 *
 * @CommerceShippingMethod(
 *  id = "ups",
 *  label = @Translation("UPS"),
 *  services = {
 *    "01" = @translation("UPS Next Day Air"),
 *    "02" = @translation("UPS Second Day Air"),
 *    "03" = @translation("UPS Ground"),
 *    "07" = @translation("UPS Worldwide Express"),
 *    "08" = @translation("UPS Worldwide Expedited"),
 *    "11" = @translation("UPS Standard"),
 *    "12" = @translation("UPS Three-Day Select"),
 *    "13" = @translation("UPS Next Day Air Saver"),
 *    "14" = @translation("UPS Next Day Air Early AM"),
 *    "54" = @translation("UPS Worldwide Express Plus"),
 *    "59" = @translation("UPS Second Day Air AM"),
 *    "65" = @translation("UPS Saver"),
 *    "70" = @translation("UPS Access Point Economy"),
 *  }
 * )
 */
class UPS extends ShippingMethodBase implements SupportsTrackingInterface {

  /**
   * The service for fetching shipping rates from UPS.
   */
  protected UPSRateRequestInterface $upsRateService;

  /**
   * The UPS SDK factory.
   */
  protected UPSSdkFactoryInterface $sdkFactory;

  /**
   * The state service.
   */
  protected StateInterface $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->upsRateService = $container->get('commerce_ups.ups_rate_request');
    $instance->upsRateService->setConfig($configuration);
    $instance->sdkFactory = $container->get('commerce_ups.ups_sdk_factory');
    $instance->state = $container->get('state');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'api_information' => [
        'account_number' => '',
        'client_id' => '',
        'secret' => '',
        'mode' => 'test',
      ],
      'rate_options' => [
        'rate_type' => 0,
        'rate_multiplier' => 1.0,
      ],
      'options' => [
        'tracking_url' => 'https://wwwapps.ups.com/tracking/tracking.cgi?tracknum=[tracking_code]',
        'round' => PHP_ROUND_HALF_UP,
        'log' => [],
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Select all services by default.
    if (empty($this->configuration['services'])) {
      $service_ids = array_keys($this->services);
      $this->configuration['services'] = array_combine($service_ids, $service_ids);
    }

    $description = $this->t('Update your UPS API information');
    if (!$this->isConfigured()) {
      $description = $this->t('Fill in your UPS API information.');
    }

    // API credentials.
    $form['api_information'] = [
      '#type' => 'details',
      '#title' => $this->t('API information'),
      '#description' => $description,
      '#weight' => $this->isConfigured() ? 10 : -10,
      '#open' => !$this->isConfigured(),
    ];
    $form['api_information']['account_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account number'),
      '#default_value' => $this->configuration['api_information']['account_number'],
      '#required' => TRUE,
    ];
    $form['api_information']['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $this->configuration['api_information']['client_id'],
      '#required' => TRUE,
    ];
    $form['api_information']['secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client secret'),
      '#default_value' => $this->configuration['api_information']['secret'],
      '#description' => $this->t('To change the current secret, enter the new one.'),
      '#required' => TRUE,
    ];
    $form['api_information']['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#description' => $this->t('Choose whether to use the test or live mode.'),
      '#options' => [
        'test' => $this->t('Test'),
        'live' => $this->t('Live'),
      ],
      '#default_value' => $this->configuration['api_information']['mode'],
    ];

    // Rate options.
    $form['rate_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Rate options'),
      '#description' => $this->t('Options to pass during rate requests.'),
    ];
    $form['rate_options']['rate_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Rate Type'),
      '#description' => $this->t('Choose between negotiated and standard rates.'),
      '#options' => [
        0 => $this->t('Standard Rates'),
        1 => $this->t('Negotiated Rates'),
      ],
      '#default_value' => $this->configuration['rate_options']['rate_type'],
    ];
    $form['rate_options']['rate_multiplier'] = [
      '#type' => 'number',
      '#title' => $this->t('Rate multiplier'),
      '#description' => $this->t('A number that each rate returned from UPS will be multiplied by. For example, enter 1.5 to mark up shipping costs to 150%.'),
      '#min' => 0.1,
      '#step' => 0.1,
      '#size' => 5,
      '#default_value' => $this->configuration['rate_options']['rate_multiplier'],
    ];

    // Options.
    $form['options'] = [
      '#type' => 'details',
      '#title' => $this->t('UPS Options'),
      '#description' => $this->t('Additional options for UPS'),
    ];
    $form['options']['tracking_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tracking URL base'),
      '#description' => $this->t(
        'The base URL for assembling a tracking URL. If the [tracking_code]
         token is omitted, the code will be appended to the end of the URL
          (e.g. "https://wwwapps.ups.com/tracking/tracking.cgi?tracknum=123456789")'
      ),
      '#default_value' => $this->configuration['options']['tracking_url'],
    ];
    $form['options']['round'] = [
      '#type' => 'select',
      '#title' => $this->t('Round type'),
      '#description' => $this->t('Choose how the shipping rate should be rounded.'),
      '#options' => [
        PHP_ROUND_HALF_UP => 'Half up',
        PHP_ROUND_HALF_DOWN => 'Half down',
        PHP_ROUND_HALF_EVEN => 'Half even',
        PHP_ROUND_HALF_ODD => 'Half odd',
      ],
      '#default_value' => $this->configuration['options']['round'],
    ];
    $form['options']['log'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Log the following messages for debugging'),
      '#options' => [
        'request' => $this->t('API request messages'),
        'response' => $this->t('API response messages'),
      ],
      '#default_value' => $this->configuration['options']['log'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);
    if ($form_state->getErrors()) {
      return;
    }
    $values = $form_state->getValue($form['#parents']);
    $api_values = $values['api_information'];
    if (empty($api_values['client_id']) || empty($api_values['secret'])) {
      return;
    }

    $sdk = $this->sdkFactory->get($api_values);
    $this->state->delete(UPSSdkFactoryInterface::TOKEN_KEY);
    try {
      $sdk->getAccessToken();
      $this->messenger()
        ->addMessage($this->t('Connectivity to UPS successfully verified.'));
    }
    catch (\Exception $e) {
      $this->messenger()
        ->addError($this->t('Invalid <em>Client ID</em> or <em>Client Secret</em> specified.'));
      $form_state->setError($form['api_information']['client_id']);
      $form_state->setError($form['api_information']['secret']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);

      $this->configuration['api_information']['account_number'] = $values['api_information']['account_number'];
      $this->configuration['api_information']['client_id'] = $values['api_information']['client_id'];
      $this->configuration['api_information']['secret'] = $values['api_information']['secret'];
      $this->configuration['api_information']['mode'] = $values['api_information']['mode'];
      $this->configuration['rate_options']['rate_type'] = $values['rate_options']['rate_type'];
      $this->configuration['rate_options']['rate_multiplier'] = $values['rate_options']['rate_multiplier'];
      $this->configuration['options']['round'] = $values['options']['round'];
      $this->configuration['options']['log'] = $values['options']['log'];
    }
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * Calculates rates for the given shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return \Drupal\commerce_shipping\ShippingRate[]
   *   The rates.
   */
  public function calculateRates(ShipmentInterface $shipment) {
    // Only attempt to collect rates if an address exists on the shipment.
    if ($shipment->getShippingProfile()->get('address')->isEmpty()) {
      return [];
    }
    if ($shipment->getPackageType() === NULL) {
      $shipment->setPackageType($this->getDefaultPackageType());
    }

    return $this->upsRateService->getRates($shipment, $this->parentEntity);
  }

  /**
   * Returns a tracking URL for UPS shipments.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The commerce shipment.
   *
   * @return mixed
   *   The URL object or FALSE.
   */
  public function getTrackingUrl(ShipmentInterface $shipment) {
    $code = $shipment->getTrackingCode();

    if (!empty($code)) {
      // If the tracking code token exists, replace it with the code.
      if (strstr($this->configuration['options']['tracking_url'], '[tracking_code]')) {
        $url = str_replace('[tracking_code]', $code, $this->configuration['options']['tracking_url']);
        return Url::fromUri($url);
      }

      // Otherwise, append the tracking code to the end of the URL.
      $url = $this->configuration['options']['tracking_url'] . $code;
      return Url::fromUri($url);
    }

    return FALSE;
  }

  /**
   * Determine if we have the minimum information to connect to UPS.
   *
   * @return bool
   *   TRUE if there is enough information to connect, FALSE otherwise.
   */
  protected function isConfigured() {
    $api_config = &$this->configuration['api_information'];

    if (empty($api_config['account_number']) || empty($api_config['client_id']) || empty($api_config['secret'])) {
      return FALSE;
    }

    return TRUE;
  }

}
