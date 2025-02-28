/**
 * @file
 * Custom library creating cross-browser support for the JS History API.
 */

Drupal.history = {};

(function (Drupal, window) {
  'use strict';

  Drupal.behaviors.historyApi = {
    attach: function () {
      Drupal.history.push = function (data, title, url) {
        if (window.history && window.history.pushState) {
          window.history.pushState(data, title, url);
        }
      };
    }
  };
}(Drupal, window));

/**
 * Ensures the JavaScript History API works consistently between browsers.
 *
 * @optimize spin this out into a file that is only called on pages that specifically
 * use the History API
 */
(function (window) {
  'use strict';

  // There's nothing to do for older browsers ;)
  if (!window.addEventListener) {
    return;
  }

  var blockPopstateEvent = document.readyState !== 'complete';

  window.addEventListener('load', function () {
    // The timeout ensures that popstate-events will be unblocked right
    // after the load event occured, but not in the same event-loop cycle.
    window.setTimeout(function () { blockPopstateEvent = false; }, 0);
  }, false);

  window.addEventListener('popstate', function (evt) {
    if (blockPopstateEvent && document.readyState === 'complete') {
      evt.preventDefault();
      evt.stopImmediatePropagation();
    }
  }, false);
}(window));
