/**
 * @file
 * Defines Javascript behaviors for the order items widget.
 */

((Drupal, once) => {
  Drupal.behaviors.commerceOrderOrderItemsWidget = {
    attach(context) {
      // Trigger the "Next|Add new order item" button when Enter is pressed in a code field.
      once(
        'order-items-widget',
        'input[name$="[add_new_item][entity_selector][purchasable_entity]"]',
        context
      ).forEach((input) =>
        input.addEventListener('keypress', (event) => {
          if (event.key !== 'Enter') {
            return;
          }
          // Prevent the browser default from being triggered.
          event.preventDefault();
          const trigger = context.querySelector(
            'input[name="oiw-order_items-add"]'
          );
          if (!trigger) {
            return;
          }
          trigger.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
        })
      );
    },
  };
})(Drupal, once);
