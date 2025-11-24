/**
 * @file
 * Defines Javascript behaviors for the cart form.
 */

(($, Drupal, drupalSettings, once) => {
  Drupal.behaviors.commerceCartForm = {
    attach(context) {
      // Trigger the "Update" button when Enter is pressed in a quantity field.
      $(
        once('commerce-cart-edit-quantity', '.quantity-edit-input', context),
      ).keydown((event) => {
        if (event.keyCode === 13) {
          // Prevent the browser default ("Remove") from being triggered.
          event.preventDefault();
          $(
            ':input#edit-submit',
            $(event.currentTarget).parents('form'),
          ).click();
        }
      });
    },
  };
})(jQuery, Drupal, drupalSettings, once);
