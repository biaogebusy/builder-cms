/**
 * @file
 *
 * Farbtastic color selector.
 */

(function (Drupal, once, $) {
  'use strict';
  Drupal.behaviors.imageEffectsFarbtasticColorSelector = {
    attach(context) {
      const elements = once('image-effects-farbtastic-color-selector', '.image-effects-farbtastic-color-selector', context);
      elements.forEach(function (index) {
        // Configure picker to be attached to the text field.
        var target = $(index).find('.image-effects-color-textfield');
        var picker = $(index).find('.farbtastic-colorpicker');
        $.farbtastic($(picker), target);
      });
    }
  };
})(Drupal, once, jQuery);
