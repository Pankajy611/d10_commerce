<?php

namespace Drupal\commerce_realex\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Payment settings form for Realex Redirect / HPP.
 */
class PaymentSettingsForm extends ConfigFormBase {

  /**
   * Config property.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * PaymentSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
    $this->config = $this->config('commerce_realex.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_realex_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('commerce_realex.settings');

    $form['realex_server_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Global Payments Server URL for payment requests'),
      '#default_value' => $config->get('realex_server_url'),
      '#required' => TRUE,
    ];

    $form['realex_merchant_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Global Payments Merchant ID'),
      '#default_value' => $config->get('realex_merchant_id'),
      '#required' => TRUE,
    ];

    $form['realex_shared_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Global Payments Shared Secret'),
      '#default_value' => $config->get('realex_shared_secret'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('commerce_realex.settings');
    $config->set(
      'realex_server_url',
      $form_state->getValue('realex_server_url')
    );
    $config->set(
      'realex_merchant_id',
      $form_state->getValue('realex_merchant_id')
    );
    $config->set(
      'realex_shared_secret',
      $form_state->getValue('realex_shared_secret')
    );
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_realex.settings',
    ];
  }

}
