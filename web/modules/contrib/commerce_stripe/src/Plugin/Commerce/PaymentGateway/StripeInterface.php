<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\profile\Entity\ProfileInterface;

/**
 * Provides the interface for the Stripe payment gateway.
 */
interface StripeInterface extends OnsitePaymentGatewayInterface, SupportsAuthorizationsInterface, SupportsRefundsInterface {

  /**
   * Get the Stripe API Publishable key set for the payment gateway.
   *
   * @return string
   *   The Stripe API publishable key.
   */
  public function getPublishableKey();

  /**
   * Create a payment intent for an order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param bool|array $intent_attributes
   *   (optional) Either an array of intent attributes or a boolean indicating
   *   whether the intent capture is automatic or manual. Passing a boolean is
   *   deprecated in 1.0-rc6. From 2.0 this parameter must be an array.
   * @param \Drupal\commerce_payment\Entity\PaymentInterface|null $payment
   *   (optional) The payment.
   *
   * @return \Stripe\PaymentIntent
   *   The payment intent.
   */
  public function createPaymentIntent(OrderInterface $order, $intent_attributes = [], PaymentInterface $payment = NULL);

  /**
   * Extracts address from the given Profile and formats it for Stripe.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The customer profile.
   * @param string $type
   *   The address type ("billing"|"shipping").
   *
   * @return array|null
   *   The formatted address array or NULL.
   *   The output array may (or may not) contain either of the following keys,
   *   depending on the data available in the profile:
   *   - name: The full name of the customer.
   *   - address: The address array.
   */
  public function getFormattedAddress(ProfileInterface $profile, $type = 'billing'): ?array;

}
