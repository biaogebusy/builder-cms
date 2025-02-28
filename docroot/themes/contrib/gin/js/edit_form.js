/* eslint-disable func-names, no-mutable-exports, comma-dangle, strict */

'use strict';

((Drupal) => {
  Drupal.behaviors.ginEditForm = {
    attach: (context) => {
      once('ginEditForm', '.region-content form.gin-node-edit-form', context).forEach(form => {
        const sticky = context.querySelector('.gin-sticky');
        const newParent = context.querySelector('.region-sticky__items__inner');

        if (newParent && newParent.querySelectorAll('.gin-sticky').length === 0) {
          newParent.appendChild(sticky);

          // Attach form elements to main form
          const actionButtons = newParent.querySelectorAll('button, input, select, textarea');
          const formLabels = newParent.querySelectorAll('label');

          if (actionButtons.length > 0) {
            actionButtons.forEach((el) => {
              el.setAttribute('form', form.getAttribute('id'));
              el.setAttribute('id', el.getAttribute('id') + '--gin-edit-form');
            });

            formLabels.forEach((el => {
              el.setAttribute('for', el.getAttribute('for') + '--gin-edit-form');
            }));
          }
        }
      });
    }
  };
})(Drupal);
