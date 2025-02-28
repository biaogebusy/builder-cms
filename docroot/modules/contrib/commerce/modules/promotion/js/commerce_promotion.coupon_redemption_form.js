/**
 * @file
 * Defines Javascript behaviors for the coupon redemption form.
 */

(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.commercePromotionCouponRedemptionForm = {
    attach: function (context) {
      // Trigger the "Apply" button when Enter is pressed in a code field.
      $(once('coupon-redemption-code', 'input[name$="[code]"]', context))
      .keydown(function (event) {
          if (event.keyCode === 13) {
            // Prevent the browser default from being triggered.
            // That is usually the "Next" checkout button.
            event.preventDefault();
            $(':input[name="apply_coupon"]', context).trigger('mousedown');
          }
        });
    }
  };
})(jQuery, Drupal, drupalSettings, once);
