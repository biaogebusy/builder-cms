/**
 * @file
 *
 * HTML color selector.
 *
 * Alters field_suffix form element after change to the color field.
 */

(function ($) {
  'use strict';
  Drupal.behaviors.imageEffectsHtmlColorSelector = {
    attach(context) {
      const elements = once('image-effects-html-color-selector', '.image-effects-html-color-selector .form-color', context);
      elements.forEach(function (index) {
        $(index).on('change', function (event) {
          var suffix = $(index).parents('.image-effects-html-color-selector').find('.form-item__suffix').get(0);
          $(suffix).text(index.value.toUpperCase());
        });
      });
    }
  };
})(jQuery);
