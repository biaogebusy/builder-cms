/**
 * jQuery.type polyfill for jQuery 4.
 *
 * Slick library (<=1.8.x) calls $.type() to detect types (array, object, string, etc).
 * jQuery 4 removed $.type(), causing Slick to fail under Drupal 11.
 * This polyfill restores the functionality for Slick.
 */
(function (window, $) {
  if (!$ || typeof $.type === 'function') {
    return;
  }

  $.type = function (obj) {
    if (obj === null) {
      return 'null';
    }

    if (Array.isArray(obj)) {
      return 'array';
    }

    return typeof obj;
  };
})(window, window.jQuery);
