/**
 * @file
 * Behaviors Border plugin layout builder form scripts.
 */

(function ($, Drupal, once) {
  "use strict";
  
  // Border.
  Drupal.behaviors.borderLayoutBuilderForm = {
    attach: function (context) {
      // The default border color.
      once('blb_border','input.bs-field-border-color', context).forEach(function (value,i) {
      var border_color = '';
        if ($(value).val() !='_none' && typeof $(value).next('label').css('border-color') != 'undefined') {
          border_color = $(value).next('label').css('border-color');
          $(value).next('label').attr('style', 'background-color: ' + border_color + ' !important; border-color: white !important;');
        }
      });

      // Assign border style.
      var directions = ['left', 'top', 'right', 'bottom'];

      // Loop through the directions.
      for (var i = 0; i < directions.length; i++) {
        var direction = directions[i];

        // Update border style.
        once('blb_border', 'input.bs-field-border-style-' + direction, context).forEach(function (value) {
          var border_style = '';
          if ($(value).val() !== '_none' && typeof $(value).next('label').css('border-style') !== 'undefined') {
            border_style = $(value).next('label').css('border-' + direction + '-style');
            $(value).next('label').css('border-style', border_style);
          }
        });

        // Switch border color to background color.
        once('blb_border', 'input.bs-field-border-color-' + direction, context).forEach(function (value) {
          var border_color = '';
          if ($(value).val() !== '_none' && typeof $(value).next('label').css('border-color') !== 'undefined') {
            border_color = $(value).next('label').css('border-' + direction + '-color');
            $(value).next('label').attr('style', 'background-color: ' + border_color + ' !important; border-color: white !important;');
          }
        });
      }

    }
  };

})(jQuery, Drupal, once);
