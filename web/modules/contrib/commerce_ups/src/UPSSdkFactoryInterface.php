<?php

namespace Drupal\commerce_ups;

/**
 * UPS SDK factory interface.
 */
interface UPSSdkFactoryInterface {

  /**
   * API url for test usage.
   */
  const UPS_INTEGRATION_BASE_URL = 'https://wwwcie.ups.com';

  /**
   * API url for production usage.
   */
  const UPS_PRODUCTION_BASE_URL = 'https://onlinetools.ups.com';

  /**
   * Token key for storage API.
   */
  const TOKEN_KEY = 'commerce_ups.oauth2_token';

  /**
   * Retrieves the UPS SDK for the given config.
   *
   * @param array $api_information
   *   An associative array, containing at least these three keys:
   *   - mode: The API mode (e.g "test" or "live").
   *   - client_id: The client ID.
   *   - secret: The client secret.
   *   - mode: The API request mode.
   */
  public function get(array $api_information): UPSSdkInterface;

}
