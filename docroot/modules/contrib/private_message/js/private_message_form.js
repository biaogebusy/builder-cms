/**
 * @file
 * Adds JS functionality to the Private Message form.
 */

((Drupal, drupalSettings, once) => {
  /**
   * @param {Event} e The event
   */
  function submitKeyPress(e) {
    const key = e.key || e.keyCode.toString();
    const supportedKeys = drupalSettings.privateMessageSendKey.split(',');
    // Remove spaces in case people separated keys by "," or ", ".
    for (let i = 0; i < supportedKeys.length; i++) {
      supportedKeys[i] = supportedKeys[i].trim();
    }

    if (supportedKeys.indexOf(key) !== -1) {
      const el = e.currentTarget;
      // If it is the send key, just remove that character from the textarea.
      const { value } = el;

      if (el.value.endsWith(key)) {
        // @todo Move this in the backend to avoid screen flicker.
        el.value = value.substring(0, value.length - 1);
      }

      if (el.value !== '') {
        el.closest('.private-message-add-form')
          ?.querySelectorAll('.form-actions .form-submit')
          .forEach((elem) => {
            elem.dispatchEvent(new Event('mousedown'));
          });
      }
    }
  }

  Drupal.behaviors.privateMessageForm = {
    attach(context) {
      once(
        'private-message-form-submit-button-listener',
        '.private-message-add-form textarea',
        context,
      ).forEach((el) => {
        el.addEventListener('keyup', submitKeyPress);
      });
    },
    detach(context) {
      // Remove event handlers when the submit button is removed from the page.
      context
        .querySelector('.private-message-add-form textarea')
        ?.removeEventListener('keyup', submitKeyPress);
    },
  };
})(Drupal, drupalSettings, once);
