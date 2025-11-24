/**
 * @file
 * Adds JavaScript functionality to the previous button.
 */

((Drupal) => {
  /**
   * Handles the "Load Previous" functionality.
   */
  class PrivateMessagePrevious {
    /**
     * Constructor to initialize IDs.
     *
     * @param {string} wrapperId
     *   The ID of the button wrapper element.
     * @param {string} buttonId
     *   The ID of the button element.
     * @param {string} wrapperClassName
     *   The wrapper class name.
     */
    constructor(wrapperId, buttonId, wrapperClassName = '') {
      this.wrapperId = wrapperId;
      this.buttonId = buttonId;
      this.wrapperClassName = wrapperClassName;
    }

    /**
     * Display the previous button.
     *
     * @param {HTMLElement} blockElement
     *   The element where the button will be added.
     * @param {Function} callback
     *   The function to call on button click.
     * @param {string} position
     *   Position: 'afterend' etc
     */
    displayButton(blockElement, callback, position = 'afterend') {
      blockElement.insertAdjacentHTML(
        position,
        `<div id="${this.wrapperId}" class = "${this.wrapperClassName}">
          <a href="#" id="${this.buttonId}" aria-label="${Drupal.t(
            'Load previous',
          )}">${Drupal.t('Load Previous')}</a>
        </div>`,
      );

      const loadPreviousButton = document.getElementById(this.buttonId);
      loadPreviousButton.addEventListener('click', callback);
    }

    /**
     * Slides down the button.
     */
    slideDownButton() {
      const buttonWrapper = document.getElementById(this.wrapperId);
      if (buttonWrapper) {
        Drupal.PrivateMessageSlide.up(buttonWrapper, 300);
      }
    }

    /**
     * Detaches event listener.
     *
     * @param {Document|HTMLElement} context
     *   The context to search for the button.
     * @param {Function} callback
     *   The function to detach from the click event.
     */
    detachEventListener(context, callback) {
      const button = context.querySelector(`#${this.buttonId}`);
      if (button) {
        button.removeEventListener('click', callback);
      }
    }
  }

  Drupal.PrivateMessagePrevious = PrivateMessagePrevious;
})(Drupal);
