/**
 * @file
 * Defines Javascript behaviors for the cart form.
 */

(function ($, Drupal, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.commerceCartForm = {
    attach: function (context) {
      // Trigger the "Update" button when Enter is pressed in a quantity field.
      $(once('commerce-cart-edit-quantity', '.quantity-edit-input', context))
      .keydown(function (event) {
          if (event.keyCode === 13) {
            // Prevent the browser default ("Remove") from being triggered.
            event.preventDefault();
            $(':input#edit-submit', $(this).parents('form')).click();
          }
        });
    }
  };
})(jQuery, Drupal, drupalSettings, once);
