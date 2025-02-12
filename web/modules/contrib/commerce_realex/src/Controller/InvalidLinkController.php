<?php

namespace Drupal\commerce_realex\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller to invalid payment links.
 */
class InvalidLinkController extends Controllerbase {

  /**
   * Displaying a page informing the user a payment link was invalid or expired.
   *
   * @return array
   *   The render array.
   */
  public function invalidLink() {
    $block['message'] = [
      '#type' => 'item',
      '#markup' => $this->t('<p><strong>Your payment may have already been processed, but something went wrong. Please contact us.</strong><p>'
        . '<p>The payment link that was used is either invalid, already used, or expired.</p>'),
    ];
    $build = [
      '#type' => 'container',
      'content' => [
        'content-wrapper' => [
          '#type' => 'container',
          'block' => $block,
        ],
      ],
    ];

    return $build;
  }

}
