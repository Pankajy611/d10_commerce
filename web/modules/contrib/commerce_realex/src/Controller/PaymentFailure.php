<?php

namespace Drupal\commerce_realex\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Controller to handle payment failures.
 */
class PaymentFailure extends Controllerbase {

  /**
   * The shared failed payment temp-store.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected $paymentFailureTempStore;

  /**
   * The payable item.
   *
   * @var \Drupal\commerce_realex\PayableItemInterface
   */
  protected $payableItem;

  /**
   * The payable item UUID.
   *
   * @var string
   */
  protected $payableItemId;

  /**
   * Route controller method.
   *
   * @param string $payable_item_id
   *   The payable item UUID.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   A render array or a RedirectResponse to an invalid link page.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   *
   * @todo ROAD-MAP:
   *   - get human-readable message from realex response
   *   - take action based on which realex response we get
   *   - invalid card details
   *   - handle other types of failure, more specific?
   */
  public function displayFailure($payable_item_id) {
    $this->paymentFailureTempStore = \Drupal::service('tempstore.shared')
      ->get('commerce_realex_failure');
    $payment = $this->paymentFailureTempStore->get('payment');
    \Drupal::logger('payment failed')
      ->error(
        'Global Payments returned:<br/><pre>' . print_r(
          $payment,
          TRUE
        ) . '</pre>'
      );

    // Redirect failure returns an array. Lightbox gives an object.
    if (!is_object($payment)) {
      $message = $payment['MESSAGE'];
    }
    else {
      $message = $payment->responseMessage;
    }

    // Replace the old PayableItem UUID with a new one and delete the old.
    try {
      $this->payableItemId = $payable_item_id;
      $payable_item = \Drupal::service('tempstore.shared')
        ->get('commerce_realex')->get($payable_item_id);

      // Redirect to page stating link is invalid, if item not found.
      if (empty($payable_item['class'])) {
        \Drupal::logger('commerce_realex')
          ->notice('Invalid Realex Payment link detected in "PaymentFailure" class for item UUID "@uuid".', [
            '@uuid' => $payable_item_id,
          ]);
        return $this->redirect('commerce_realex.link_invalid');
      }

      $this->payableItem = $payable_item['class']::createFromPaymentTempStore($payable_item_id);
      // Delete used payableItem from temp store.
      $this->payableItem->deleteFromPaymentTempStore($payable_item_id);
    }
    catch (\Exception $e) {
      \Drupal::logger('commerce_realex')->error($e->getMessage());
    }

    // Generated a new UUID - as Global Payment need each attempt to be unique.
    $uuid_service = \Drupal::service('uuid');
    $uuid = $uuid_service->generate();

    // Save it to shared temp store under the UUID "payment object" key.
    $this->payableItem->setValue('link_used', FALSE);
    $this->payableItem->saveTempStore($uuid);

    $url = Url::fromRoute(
      'commerce_realex.payment_retry',
      ['payable_item_id' => $uuid]
    );
    $add_link = Link::fromTextAndUrl($this->t('click here'), $url);
    $add_link = $add_link->toString();
    $block['message2'] = [
      '#type' => 'item',
      '#markup' => $this->t(
        'Message from Payment provider<br/><strong>@message</strong><br/>',
        ['@message' => $message]
      ),
    ];
    $block['message'] = [
      '#type' => 'item',
      '#markup' => $this->t(
        'Your payment was unsuccessful.<br/>Please @add_link to retry.',
        ['@add_link' => $add_link]
      ),
    ];

    $build = [
      '#type' => 'container',
      'content' => [
        'content-wrapper' => [
          '#type' => 'container',
          'block' => $block,
          '#attributes' => [
            'class' => ['realex-response'],
          ],
        ],
      ],
    ];

    return $build;
  }

  /**
   * Allow a payment to be retried.
   *
   * @param string $payable_item_id
   *   The payable item UUID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A RedirectResponse Object to the Global Payments servers.
   */
  public function retryPayment($payable_item_id) {
    return $this->redirect(
      'commerce_realex.payment_form',
      ['payable_item_id' => $payable_item_id]
    );
  }

}
