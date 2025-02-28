/**
 * @file
 *   Main JavaScript file for Dismiss module
 */

/* eslint-disable func-names, no-mutable-exports, comma-dangle, strict */

((Drupal, once) => {
  Drupal.behaviors.ginMessages = {
    attach: (context) => {
      Drupal.ginMessages.dismissMessages(context);
    }
  };

  Drupal.ginMessages = {
    dismissMessages: (context = document) => {
      once('gin-messages-dismiss', '.messages .button--dismiss', context).forEach(dismissButton => {
        dismissButton.addEventListener('click', e => {
          e.preventDefault();
          const message = e.currentTarget.closest('.messages-list__item');
          Drupal.ginMessages.hideMessage(message);
        });
      });
    },

    hideMessage: (message) => {
      message.style.opacity = 0;
      message.classList.add('visually-hidden');
    },
  };
})(Drupal, once);
