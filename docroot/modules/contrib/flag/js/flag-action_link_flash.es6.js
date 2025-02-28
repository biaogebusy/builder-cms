/**
 * @file
 * Defines the client side code for the ActionLinkFlashCommand.
 *
 * see \Drupal\flag\Ajax\ActionLinkFlashCommand
 */

(function (Drupal) {
  // As flag action links are added to the DOM attach a click handler to each
  // link so that styles can be applied while the XHR is in flight.
  Drupal.behaviors.flagAttach = {
    attach: (context) => {
      const links = [...context.querySelectorAll('.flag a')];
      links.forEach(link => link.addEventListener('click', event => event.target.parentNode.classList.add('flag-waiting')));
    },
  };

  /**
   * Display message on the screen for a short period of time.
   *
   * @param {Drupal.Ajax} [ajax]
   *   The ajax object.
   * @param {object} response
   *   Object holding the server response.
   * @param {string} response.selector
   *   The selector of link to be updated.
   * @param {string} response.message
   *   The message to be displayed.
   * @param {number} [status]
   *   The HTTP status code.
   */
  Drupal.AjaxCommands.prototype.actionLinkFlash = (ajax, response, status) => {
    if (status === 'success') {
      if (response.message.length) {
        // Prepare a message element.
        const para = document.createElement('P');
        para.innerText = response.message;
        // Adding this class will initiate a CSS transition.
        para.setAttribute('class', 'js-flag-message');
        // As the transition ends delete the message from the DOM.
        para.addEventListener('animationend', event => event.target.remove(), false);

        // Add message element to the DOM.
        document.querySelector(response.selector).appendChild(para);
      }
    }
    else {
      // If the XHR failed, assume the replace command that would normally make
      // the styling disapear has also failed and remove the temporary styling.
      const links = [...document.querySelectAll('.flag-waiting')];
      links.forEach(link => link.classList.remove('flag-waiting'));
    }
  };
})(Drupal);
