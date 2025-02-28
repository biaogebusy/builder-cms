/**
 * @file
 *
 * JQuery Colorpicker color selector.
 *
 * Alters field_suffix form element after change to the color field.
 */

(function (Drupal, once, $) {
  'use strict';
  Drupal.behaviors.imageEffectsJqueryColorpickerColorSelector = {
    attach(context) {
      const elements = once('image-effects-jquery-colorpicker-color-selector', '.image-effects-jquery-colorpicker-color-selector .image-effects-jquery-colorpicker', context);
      elements.forEach(function (index) {
        $(index).parent().append('<span class="image-effects-color-suffix">' + index.value.toUpperCase() + '</div>');
        $(index).on('change', function (event) {
          var suffix = $(index).parent().find('.image-effects-color-suffix').get(0);
          $(suffix).text(index.value.toUpperCase());
        });
      });
    }
  };
})(Drupal, once, jQuery);
