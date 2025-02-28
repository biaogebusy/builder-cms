/**
 * @file
 * Behaviors of Boostrap Layout Builder local video background.
 */

(function ($, _, Drupal, drupalSettings) {
  "use strict";

  Drupal.behaviors.backgroundMediaBuildLocalVideoBG = {
    attach: function (context, settings) {
      // Set the height of the background video.
      $('.background-local-video').each(function() {
        $(this).height(function() {
          return $(this).find('.video-content > div').outerHeight();
        });
      });
    }
  }

})(window.jQuery, window._, window.Drupal, window.drupalSettings);
