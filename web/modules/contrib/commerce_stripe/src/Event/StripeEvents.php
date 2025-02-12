<?php

namespace Drupal\commerce_stripe\Event;

/**
 * Defines events for the Commerce Stripe module.
 */
class StripeEvents {

  /**
   * Name of the event fired before the PaymentIntent is created.
   *
   * This allows subscribers to add or modify intent attributes and metadata.
   *
   * @Event
   *
   * @see https://stripe.com/docs/api/payment_intents/create
   * @see \Drupal\commerce_stripe\Event\PaymentIntentEvent
   */
  public const PAYMENT_INTENT_CREATE = 'commerce_stripe.payment_intent_create';

  /**
   * Name of the event fired to add additional transaction data.
   *
   * @deprecated in commerce_stripe:8.x-1.0 and is removed from commerce_stripe:2.0.0.
   * Use StripeEvents::PAYMENT_INTENT_CREATE
   * to modify the payment intent attributes.
   *
   * @see https://www.drupal.org/project/commerce_stripe/issues/3412438
   *
   * @see https://stripe.com/blog/adding-context-with-metadata
   * @see https://stripe.com/docs/api#metadata
   * @see \Drupal\commerce_stripe\Event\TransactionDataEvent
   *
   * @Event
   */
  public const TRANSACTION_DATA = 'commerce_stripe.transaction_data';

  /**
   * Name of the event fired when a payment method is about to be created.
   *
   * This event is triggered by commerce_stripe.form.js when the checkout
   * payment information form submit button is clicked. It is dispatched
   * before any Drupal form submit handlers run, before the remote payment
   * method is created at Stripe, and before the local Commerce payment method
   * entity is saved. Subscribers may use this event to customize the remote
   * payment method setup at Stripe.
   *
   * @Event
   *
   * @see \Drupal\commerce_stripe\Event\PaymentMethodCreateEvent
   */
  public const PAYMENT_METHOD_CREATE = 'commerce_stripe.payment_method_create';

}
