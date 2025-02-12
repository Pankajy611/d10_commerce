<?php

namespace Drupal\commerce_ups;

use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;
use Sainsburys\Guzzle\Oauth2\AccessToken;
use Sainsburys\Guzzle\Oauth2\GrantType\ClientCredentials as BaseClientCredentials;

/**
 * Client credentials grant type.
 */
class ClientCredentials extends BaseClientCredentials {

  /**
   * The state service.
   */
  protected StateInterface $state;

  /**
   * Constructs a new ClientCredentials object.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The client.
   * @param array $config
   *   The configuration.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(ClientInterface $client, array $config, StateInterface $state) {
    parent::__construct($client, $config);

    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function getToken(): AccessToken {
    $token = parent::getToken();

    // Store the token retrieved for later reuse (to make sure we don't request
    // for a new one on each API request).
    $this->state->set($this->config['token_key'], [
      'token' => $token->getToken(),
      'type' => $token->getType(),
      'expires' => $token->getExpires()->getTimestamp(),
    ]);

    return $token;
  }

  /**
   * Helper method to clear the token, so it's not persisted after a failure.
   */
  public function clearToken(): void {
    $this->state->delete($this->config['token_key']);
  }

}
