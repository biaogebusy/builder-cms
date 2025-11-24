/**
 * @file
 *
 * Slide functions for Private Message module.
 */

((Drupal) => {
  Drupal.PrivateMessageSlide = Drupal.PrivateMessageSlide || {};

  /**
   * Displays the matched elements by fading them to opaque.
   *
   * @param {HTMLElement} element
   *   The element to toggle.
   * @param {int} duration
   *   Number determining how long the animation will run.
   */
  Drupal.PrivateMessageSlide.fadeIn = (element, duration = 500) => {
    element.style.opacity = '0';
    element.style.display = 'block';
    element.style.transition = `opacity ${duration}ms`;

    requestAnimationFrame(() => {
      element.style.opacity = '1';
    });
  };

  /**
   * Displays the matched elements with a sliding motion.
   *
   * @param {HTMLElement} element
   *   The element to toggle.
   * @param {int} duration
   *   Number determining how long the animation will run.
   */
  Drupal.PrivateMessageSlide.down = (element, duration = 500) => {
    element.style.display = 'block';
    const height = `${element.scrollHeight}px`;
    element.style.height = '0';
    element.style.overflow = 'hidden';
    element.style.transition = `height ${duration}ms ease-out`;

    requestAnimationFrame(() => {
      element.style.height = height;
    });

    setTimeout(() => {
      element.style.height = '';
      element.style.overflow = '';
      element.style.transition = '';
    }, duration);
  };

  /**
   * Hide the matched elements with a sliding motion.
   *
   * @param {HTMLElement} element
   *   The element to toggle.
   * @param {int} duration
   *   Number determining how long the animation will run.
   */
  Drupal.PrivateMessageSlide.up = (element, duration = 500) => {
    const height = `${element.scrollHeight}px`;
    element.style.height = height;
    element.style.overflow = 'hidden';
    element.style.transition = `height ${duration}ms ease-out`;

    requestAnimationFrame(() => {
      element.style.height = '0';
    });

    setTimeout(() => {
      element.style.display = 'none';
      element.style.height = '';
      element.style.overflow = '';
      element.style.transition = '';
    }, duration);
  };
})(Drupal);
