/**
 * @file
 *
 * Farbtastic color selector.
 *
 * @todo remove this once Drupal 9 is no longer supported.
 */

(function ($) {
  'use strict';
  Drupal.behaviors.imageEffectsFarbtasticColorSelector = {
    attach: function (context, settings) {
      $('.image-effects-farbtastic-color-selector', context).once('image-effects-farbtastic-color-selector').each(function (index) {
        // Configure picker to be attached to the text field.
        var target = $(this).find('.image-effects-color-textfield');
        var picker = $(this).find('.farbtastic-colorpicker');
        $.farbtastic($(picker), target);
      });
    }
  };
})(jQuery);
