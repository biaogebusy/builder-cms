/**
 * @file
 * Defines Javascript behaviors for the order item edit form.
 */

((Drupal, once) => {
  Drupal.behaviors.commerceOrderOrderItemEditForm = {
    attach() {
      // Modify formdata before sending it for saving.
      once('order-item-edit-form', '.commerce-order-item-form').forEach(
        (form) =>
          form.addEventListener('formdata', (e) => {
            const fields = [
              'quantity[0][value]',
              'unit_price[0][amount][number]',
            ];
            fields.forEach((fieldName) => {
              const value = e.formData.get(fieldName);
              const input = e.target.elements[fieldName];
              const newValue = input.value;
              if (value !== newValue) {
                e.formData.set(fieldName, newValue);
              }
            });
          })
      );
    },
  };
})(Drupal, once);
