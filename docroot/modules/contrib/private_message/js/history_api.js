/**
 * @file
 * Custom library creating cross-browser support for the JS History API.
 */

Drupal.history = {};

((Drupal, window) => {
  Drupal.behaviors.historyApi = {
    attach() {
      Drupal.history.push = (data, title, url) => {
        if (window.history && window.history.pushState) {
          window.history.pushState(data, title, url);
        }
      };
    },
  };
})(Drupal, window);

/**
 * Ensures the JavaScript History API works consistently between browsers.
 *
 * @optimize spin this out into a file that is only called on pages that specifically
 * use the History API
 */
((window) => {
  // There's nothing to do for older browsers ;)
  if (!window.addEventListener) {
    return;
  }

  let blockPopstateEvent = document.readyState !== 'complete';

  window.addEventListener(
    'load',
    () => {
      // The timeout ensures that popstate-events will be unblocked right
      // after the load event occurred, but not in the same event-loop cycle.
      window.setTimeout(() => {
        blockPopstateEvent = false;
      }, 0);
    },
    false,
  );

  window.addEventListener(
    'popstate',
    (evt) => {
      if (blockPopstateEvent && document.readyState === 'complete') {
        evt.preventDefault();
        evt.stopImmediatePropagation();
      }
    },
    false,
  );
})(window);
