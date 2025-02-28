/**
 * @file
 * Behaviors Text Color plugin layout builder form scripts.
 */

(function ($, Drupal, once) {
  "use strict";
  
  // Text color.
  Drupal.behaviors.textColorLayoutBuilderForm = {
    attach: function (context) {

      once('blb_text-color',".fieldgroup.field-text-color input[type=radio]", context).forEach(function (value,i) {
        $(value).next('label').addClass($(value).val());

        // Attach the color as a background color to the label AFTER adding the class.
        if($(value).val() != '_none') {
          var label_color = $(value).next('label').css('color');
          $(value).next('label').css('background-color', label_color);

          // Set a contrast class so we can see our checkmarks on light vs. dark backgrounds.
          var bgColor = $(value).next('label').css('background-color');
          var bgColorHex = rgb2hex(bgColor);
          var bgColorContrast = getContrast(bgColorHex);
          $(value).next('label').addClass('bs_yiq-' + bgColorContrast);
        }
      });

      $(".fieldgroup.field-text-color .fieldset-wrapper label", context).on('click', function () {

        $(this).parents('.fieldset-wrapper').find('label').removeClass('active');
        // Temp comment the following line because of conflict with live preview.
        // $(this).parents('.fieldset-wrapper').addClass('style-selected').find('input').prop("checked", false);
        // $(this).parent().find('input').prop('checked', true);

        if($(this).hasClass('_none')) {
          $(this).parents('.fieldset-wrapper').removeClass('style-selected');
        }
      });

      // Custom solution for bootstrap 3 & Bario drupal theme issues.
      $(".fieldgroup.field-text-color .fieldset-wrapper input:radio", context).each(function () {
        $(this).closest('.radio').find('label').addClass($(this).val());
        var checked = $(this).prop("checked");
        if (typeof checked !== typeof undefined && checked !== false) {
          $(this).closest('.radio').find('label').addClass('active');
        }
      });
    }
  };

})(jQuery, Drupal, once);
