<?php

namespace Drupal\commerce_cart;

/**
 * Provides the interface for the cart cron.
 *
 * @deprecated in commerce:8.x-2.40 and is removed from commerce:3.x. Use
 *   Drupal\commerce\CronInterface.
 * @see https://www.drupal.org/project/commerce/issues/2863864
 */
interface CronInterface {

  /**
   * Runs the cron.
   */
  public function run();

}
