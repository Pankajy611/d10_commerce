/**
 * @file
 * Defines behaviors for the Stripe Payment Element payment method form.
 */

(($, Drupal, drupalSettings, Stripe) => {
  /**
   * Attaches the commerceStripePaymentElement behavior.
   */
  Drupal.behaviors.commerceStripePaymentElement = {
    attach(context) {
      if (
        !drupalSettings.commerceStripePaymentElement ||
        !drupalSettings.commerceStripePaymentElement.publishableKey
      ) {
        return;
      }

      const settings = drupalSettings.commerceStripePaymentElement;
      function processStripeForm() {
        const $form = $(this).closest('form');
        const $primaryButton = $form.find(':input.button--primary');

        const stripeOptions = {};
        if (settings.apiVersion) {
          stripeOptions.apiVersion = settings.apiVersion;
        }
        // Create a Stripe client.
        const stripe = Stripe(settings.publishableKey, stripeOptions);

        // Show Stripe Payment Element form.
        if (settings.showPaymentForm) {
          // Create an instance of Stripe Elements.
          const elements = stripe.elements(settings.createElementsOptions);
          const paymentElement = elements.create(
            'payment',
            settings.paymentElementOptions,
          );
          paymentElement.mount(`#${settings.elementId}`);
          paymentElement.on('ready', () => {
            $primaryButton.prop('disabled', false).removeClass('is-disabled');
          });

          $form.on('submit.stripe_payment_element', (e) => {
            e.preventDefault();
            $primaryButton.prop('disabled', true);
            let stripeConfirm = stripe.confirmPayment;
            if (settings.intentType === 'setup') {
              stripeConfirm = stripe.confirmSetup;
            }
            stripeConfirm({
              elements,
              confirmParams: {
                return_url: settings.returnUrl,
              },
            }).then((result) => {
              if (result.error) {
                // Inform the user if there was an error.
                // Display the message error in the payment form.
                Drupal.commerceStripe.displayError(result.error.message);
                // Allow the customer to re-submit the form.
                $primaryButton.prop('disabled', false);
              }
            });
          });
        }
        // Confirm a payment by payment method.
        else {
          $primaryButton.prop('disabled', false).removeClass('is-disabled');
          let allowSubmit = false;
          $form.on('submit.stripe_payment_element', () => {
            $primaryButton.prop('disabled', true);
            if (!allowSubmit) {
              $primaryButton.prop('disabled', true);
              let stripeConfirm = stripe.confirmPayment;
              if (settings.intentType === 'setup') {
                stripeConfirm = stripe.confirmSetup;
              }
              stripeConfirm({
                clientSecret: settings.clientSecret,
                confirmParams: {
                  return_url: settings.returnUrl,
                },
                redirect: 'always',
              }).then((result) => {
                if (result.error) {
                  Drupal.commerceStripe.displayError(result.error.message);
                  $primaryButton.prop('disabled', false);
                } else {
                  allowSubmit = true;
                  $primaryButton.prop('disabled', false);
                  $form.get(0).requestSubmit($primaryButton.get(0));
                }
              });
              return false;
            }
            return true;
          });
        }
      }
      $(once('stripe-processed', `#${settings.elementId}`, context)).each(
        processStripeForm,
      );
    },
    detach: (context, settings, trigger) => {
      if (trigger !== 'unload') {
        return;
      }
      const $form = $(
        `[id^=${drupalSettings.commerceStripePaymentElement.elementId}]`,
        context,
      ).closest('form');
      if ($form.length === 0) {
        return;
      }
      $form.off('submit.stripe_payment_element');
    },
  };
})(jQuery, Drupal, drupalSettings, window.Stripe);
