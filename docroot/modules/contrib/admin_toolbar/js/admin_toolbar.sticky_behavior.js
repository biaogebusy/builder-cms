/**
 * @file
 * Admin Toolbar sticky behavior JS functions.
 */

((once) => {
  /**
   * Implements the Admin Toolbar configured 'sticky_behavior'.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for the scroll up/down logic.
   */
  Drupal.behaviors.adminToolbarStickyBehavior = {
    attach: (context) => {
      if (context !== document) {
        return;
      }

      // Attach to the 'body' an event listener for the page scroll to implement
      // the 'Hide on scroll, show on scroll up' behavior, by adding the CSS
      // class 'sticky-toolbar-hidden' to the 'body' tag of the page.
      once('admin-toolbar-sticky-behavior', 'body', context).forEach(
        (element) => {
          // The delta is the minimum amount of scroll in pixels before the
          // toolbar is hidden or shown. This is to prevent the toolbar from
          // flickering when the user scrolls up and down quickly.
          const delta = 10;
          // Initialize lastScrollTop, which is the last scroll position of the
          // page and is used to determine if the user is scrolling up or down.
          let lastScrollTop = 0;

          window.addEventListener('scroll', () => {
            if (
              localStorage.getItem(
                'Drupal.adminToolbar.toggleToolbarHidden',
              ) === 'true'
            ) {
              // If the toolbar is hidden, do not execute the scroll logic.
              return;
            }
            // Get the current scrollTop position from the document.
            const { scrollTop } = document.scrollingElement;
            // Ensure user scrolled more than delta. The abs() is used to
            // enforce the delta distance in pixels in both directions: up/down.
            if (Math.abs(lastScrollTop - scrollTop) <= delta) return;
            // If the recorded scroll is greater than delta, change the state.
            if (scrollTop > lastScrollTop) {
              // Scrolling down: Hide the toolbar.
              element.classList.add('sticky-toolbar-hidden');
            } else {
              // Scrolling up: Show the toolbar, by restoring its default state.
              element.classList.remove('sticky-toolbar-hidden');
            }
            lastScrollTop = scrollTop;
          });
        },
      );
    },
  };
})(once);
