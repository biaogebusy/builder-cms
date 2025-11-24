/**
 * @file
 * Adds JavaScript functionality to the dimmer.
 */

((Drupal) => {
  /**
   * Handles the "Dimmer" functionality.
   */
  Drupal.PrivateMessageDimmer = {
    dimmerClass: 'private-message-dimmer',
    fader: null,

    /**
     * Shows the dimmer.
     *
     * @param {HTMLElement} element
     *   The element where the dimmer will be added.
     */
    showDimmer(element) {
      let dimmer = this.getDimmer();
      if (!dimmer) {
        dimmer = document.createElement('div');
        dimmer.classList.add(this.dimmerClass);
        element.appendChild(dimmer);

        this.fader = new Drupal.PrivateMessageFader(dimmer);
      }

      if (this.fader) {
        this.fader.fadeTo(500, 0.8);
      }
    },

    /**
     * Hides the dimmer.
     */
    hideDimmer() {
      if (!this.fader) {
        return;
      }

      this.fader.fadeOut(500);
    },

    /**
     * Gets dimmer.
     *
     * @return {HTMLElement}
     *   The dimmer element, if found.
     */
    getDimmer() {
      return document.querySelector(`.${this.dimmerClass}`);
    },
  };
})(Drupal);
