/**
 * @file
 * Adds JavaScript functionality for fader.
 */

((Drupal) => {
  /**
   * Handles the "Fader" functionality.
   */
  class PrivateMessageFader {
    constructor(element) {
      this.element = element;
      this.queue = Promise.resolve();
    }

    /**
     * Shows the element.
     */
    _ensureVisible() {
      const style = getComputedStyle(this.element);
      if (style.display === 'none') {
        this.element.style.display = '';

        if (getComputedStyle(this.element).display === 'none') {
          this.element.style.display = 'block';
        }
      }
    }

    /**
     * Creates a Promise-based animation.
     *
     * @param {number} duration
     *   Duration
     * @param {function} callback
     *   Callback function.
     *
     * @return {Promise<unknown>}
     *   Promise.
     */
    static _animate(duration, callback) {
      return new Promise((resolve) => {
        const startTime = performance.now();

        const animate = (time) => {
          const elapsedTime = time - startTime;
          const progress = Math.min(elapsedTime / duration, 1);
          callback(progress);

          if (progress < 1) {
            requestAnimationFrame(animate);
          } else {
            resolve();
          }
        };

        requestAnimationFrame(animate);
      });
    }

    /**
     * Fades to a specific opacity
     *
     * @param {number} duration
     *   Duration.
     * @param {number} targetOpacity
     *   Target opacity.
     */
    fadeTo(duration, targetOpacity) {
      this.queue = this.queue.then(() => {
        this._ensureVisible();
        const startOpacity = parseFloat(getComputedStyle(this.element).opacity);

        return PrivateMessageFader._animate(duration, (progress) => {
          this.element.style.opacity =
            startOpacity + progress * (targetOpacity - startOpacity);
        });
      });
    }

    /**
     * Fades out to opacity 0 and hide the element
     *
     * @param {number} duration
     *   Duration.
     */
    fadeOut(duration) {
      this.queue = this.queue.then(() => {
        const startOpacity = parseFloat(getComputedStyle(this.element).opacity);

        return PrivateMessageFader._animate(duration, (progress) => {
          this.element.style.opacity = startOpacity - progress * startOpacity;

          if (progress === 1) {
            this.element.style.display = 'none';
          }
        });
      });
    }
  }

  Drupal.PrivateMessageFader = PrivateMessageFader;
})(Drupal);
