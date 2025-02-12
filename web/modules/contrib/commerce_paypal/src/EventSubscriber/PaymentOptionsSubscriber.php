<?php

namespace Drupal\commerce_paypal\EventSubscriber;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Event\FilterPaymentOptionsEvent;
use Drupal\commerce_payment\Event\PaymentEvents;
use Drupal\commerce_payment\PaymentOption;
use Drupal\commerce_paypal\Plugin\Commerce\PaymentGateway\CheckoutInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines an event subscriber altering PayPal checkout option labels.
 */
class PaymentOptionsSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new PaymentOptionsSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PaymentEvents::FILTER_PAYMENT_OPTIONS => ['onFilterPaymentOptions', -100],
    ];
  }

  /**
   * Alters the PayPal Checkout option labels.
   *
   * PayPal Checkout allows customizing the payment gateway display name but
   * because of the way it is currently implemented, the display label isn't
   * used. This code was written to address that.
   *
   * @param \Drupal\commerce_payment\Event\FilterPaymentOptionsEvent $event
   *   The filter payment options event.
   */
  public function onFilterPaymentOptions(FilterPaymentOptionsEvent $event): void {
    $options = $event->getPaymentOptions();
    /** @var \Drupal\commerce_payment\PaymentGatewayStorageInterface $payment_gateway_storage */
    $payment_gateway_storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');
    /** @var \Drupal\commerce_payment\PaymentMethodStorageInterface $payment_method_storage */
    $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
    foreach ($options as $key => $option) {
      /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
      $payment_gateway = $payment_gateway_storage->load($option->getPaymentGatewayId());
      $payment_gateway_plugin = $payment_gateway?->getPlugin();
      if (!$payment_gateway_plugin instanceof CheckoutInterface) {
        continue;
      }
      // In case the option references an existing payment method, ensure
      // skip retitling the payment option if a remote ID is set as we'll
      // be using the card type and the last digits of the card as the option
      // label.
      if (!empty($option->getPaymentMethodId())) {
        $payment_method = $payment_method_storage->load($option->getPaymentMethodId());
        if ($payment_method instanceof PaymentMethodInterface && !empty($payment_method->getRemoteId())) {
          continue;
        }
      }
      $retitled_option = new PaymentOption([
        'label' => $payment_gateway_plugin->getDisplayLabel(),
      ] + $option->toArray());
      $options[$key] = $retitled_option;
    }
    $event->setPaymentOptions($options);
  }

}
