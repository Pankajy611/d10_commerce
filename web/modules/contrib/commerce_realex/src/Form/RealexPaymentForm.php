<?php

namespace Drupal\commerce_realex\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * The Global Payments payment form displayed to end user.
 *
 * @todo Does this actually need to be a FormBase?
 *   We aren't submitting it to Drupal!
 *   Button is a html_tag <button>, not a FAPI submit.
 *   Route could use a _controller instead of _form?
 */
class RealexPaymentForm extends FormBase {

  /**
   * The shared payment temp-store.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected $paymentSharedTempStore;

  /**
   * The payable item.
   *
   * @var \Drupal\commerce_realex\PayableItemInterface
   */
  protected $payableItem;

  /**
   * An UUID for a PayableItemInterface object.
   *
   * @var string
   */
  protected $payableItemId;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_realex_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $payable_item_id = NULL
  ) {
    try {
      $this->payableItemId = $payable_item_id;
      // @todo - Generalise when new payments come on board.
      $this->paymentSharedTempStore = \Drupal::service('tempstore.shared')
        ->get('commerce_realex');
      $this->payableItem = $this->paymentSharedTempStore->get($payable_item_id);
    }
    catch (\Exception $e) {
      \Drupal::logger('commerce_realex')->error($e->getMessage());
    }

    // Redirect to page stating link is invalid, if item not found.
    if (empty($this->payableItem)) {
      \Drupal::logger('commerce_realex')
        ->notice('Invalid Realex Payment link detected in "RealexPaymentForm" class for item UUID "@uuid".', [
          '@uuid' => $payable_item_id,
        ]);
      return $this->redirect('commerce_realex.link_invalid');
    }

    $form_fields = [];

    $payable = $this->payableItem['values'];
    $realex_config = $payable['realex_config'];

    // Display a summary of what is being paid for.
    $currency_formatter = \Drupal::service('commerce_price.currency_formatter');
    $form_fields['summary'] = [
      '#type' => 'item',
      '#markup' => $currency_formatter->format(
        $payable['payable_amount'],
        $payable['payable_currency']
      ),
      '#title' => $this->t('Summary:'),
    ];

    // Display a warning to wait until the redirect from Global Payments back to
    // the site is done.
    $form_fields['realex_warning'] = [
      '#markup' => $this->t(
        'Please wait until the payment has fully completed after filling out your credit card credentials.<br>This way you can be sure your order will be correctly processed by us.'
      ),
    ];

    $pay_button_id = Html::getUniqueId('realex-pay-button');
    $form_buttons['commerce_realex_button'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $this->t('Proceed to secure payment'),
      '#prefix' => '<div class="button-wrapper">',
      '#suffix' => '</div>',
      '#attributes' => [
        'type' => 'button',
        'id' => $pay_button_id,
        'class' => [
          'button',
          'payment-button',
        ],
      ],
    ];

    $form['wrapper'] = [
      '#type' => 'container',
      'content' => [
        'content-wrapper' => [
          '#type' => 'container',
          'form' => $form_fields,
          'form_buttons' => $form_buttons,
        ],
      ],
    ];

    // Prepare URLs which which rxp-js needs.
    $request_url = Url::fromRoute(
      'commerce_realex.payment_request',
      ['payable_item_id' => $this->payableItemId],
      ['absolute' => TRUE]
    )
      ->toString();

    $response_url = Url::fromRoute(
      'commerce_realex.payment_response',
      ['payable_item_id' => $this->payableItemId],
      ['absolute' => TRUE]
    )
      ->toString();

    $form['#attached']['drupalSettings']['realexPaymentForm'] = [
      'payButtonId' => $pay_button_id,
      'hppUrl' => $realex_config['realex_server_url'],
      'responseUrl' => $response_url,
      'requestUrl' => $request_url,
      'paymentMethod' => $realex_config['realex_payment_method'],
    ];
    $form['#attached']['library'][] = 'commerce_realex/realex-rxpjs';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Nothing to do here.
    // We are actually using JS to send a request to Global Payments, then
    // redirect to our Global Payments Response controller.
  }

}
