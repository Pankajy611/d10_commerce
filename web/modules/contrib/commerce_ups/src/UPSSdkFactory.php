<?php

namespace Drupal\commerce_ups;

use Drupal\Core\Http\ClientFactory;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use Sainsburys\Guzzle\Oauth2\GrantType\GrantTypeBase;
use Sainsburys\Guzzle\Oauth2\Middleware\OAuthMiddleware;

/**
 * Defines a factory for our custom UPS SDK.
 */
class UPSSdkFactory implements UPSSdkFactoryInterface {

  /**
   * Array of all instantiated PayPal Checkout SDKs.
   *
   * @var \Drupal\commerce_ups\UPSSdkInterface[]
   */
  protected array $instances = [];

  /**
   * Constructs a new UPSSdkFactory object.
   *
   * @param \Drupal\Core\Http\ClientFactory $clientFactory
   *   The client factory.
   * @param \GuzzleHttp\HandlerStack $stack
   *   The handler stack.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\commerce_ups\UPSSdkInterface $upsSdk
   *   The UPS SDK service.
   */
  public function __construct(
    protected ClientFactory $clientFactory,
    protected HandlerStack $stack,
    protected StateInterface $state,
    protected UPSSdkInterface $upsSdk,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function get(array $api_information): UPSSdkInterface {
    $instance_key = md5(serialize($api_information));
    if (!isset($this->instances[$instance_key])) {
      $client = $this->getClient($api_information);
      $sdk_instance = clone $this->upsSdk;
      $sdk_instance->setConfiguration($api_information);
      $sdk_instance->setClient($client);
      $this->instances[$instance_key] = $sdk_instance;
    }

    return $this->instances[$instance_key];
  }

  /**
   * Gets a preconfigured HTTP client instance for use by the SDK.
   *
   * @param array $api_information
   *   The configurations.
   */
  protected function getClient(array $api_information): ClientInterface {
    $base_uri = $api_information['mode'] === 'live' ? self::UPS_PRODUCTION_BASE_URL : self::UPS_INTEGRATION_BASE_URL;
    $options = [
      'base_uri' => $base_uri,
      'headers' => [
        'Content-Type' => 'application/json',
      ],
    ];
    $client = $this->clientFactory->fromOptions($options);
    // Generates a key for storing the OAuth2 token retrieved from UPS.
    // This is useful in case multiple UPS shipping method instances are
    // configured.
    $token_key = self::TOKEN_KEY . '.' . md5($api_information['client_id'] . $api_information['secret']);
    $config = [
      GrantTypeBase::CONFIG_CLIENT_ID => $api_information['client_id'],
      GrantTypeBase::CONFIG_CLIENT_SECRET => $api_information['secret'],
      GrantTypeBase::CONFIG_TOKEN_URL => UPSSdkInterface::UPS_ACCESS_TOKEN_URL,
      'token_key' => $token_key,
    ];
    $grant_type = new ClientCredentials($client, $config, $this->state);
    $middleware = new OAuthMiddleware($client, $grant_type);

    // Check if we've already requested an OAuth2 token, note that we do not
    // need to check for the expired timestamp here as the middleware is already
    // taking care of that.
    $token = $this->state->get($token_key, FALSE);
    if ($token) {
      $middleware->setAccessToken($token['token'], $token['type'], $token['expires']);
    }
    $this->stack->push($middleware->onBefore());
    $this->stack->push($middleware->onFailure(2));
    $options['handler'] = $this->stack;
    $options['auth'] = 'oauth2';
    return $this->clientFactory->fromOptions($options);
  }

}
