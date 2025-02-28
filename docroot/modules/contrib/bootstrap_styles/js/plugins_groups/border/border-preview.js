/**
 * @file
 * Behaviors border plugin group.
 */

(function ($, Drupal, once) {
  "use strict";
  
  // Border preview box.
  Drupal.behaviors.borderPreview = {
    attach: function (context,settings) {
      var border_width = settings.bootstrap_styles.border.border_width;
      var rounded_corners = settings.bootstrap_styles.border.rounded_corners;
      var directions = ['left', 'top', 'right', 'bottom'];
      var corners = ['top_left', 'top_right', 'bottom_left', 'bottom_right'];

      // Refresh preview Classes.
      function refreshPreviewClasses() {
        var border_classes = '';

        // Border style.
        $('input.bs-field-border-style').each(function() {
          if ($(this).is(':checked') && $(this).val() != '_none') {
            border_classes += $(this).val() + ' ';
          }
        });

        // Border width.
        var border_width_val = $('input.bs-field-border-width').val();
        var border_width_class = border_width.border_width[border_width_val];
        if (border_width_class != '_none') {
          border_classes += border_width_class + ' ';
        }

        // Border color.
        $('input.bs-field-border-color').each(function() {
          if ($(this).is(':checked') && $(this).val() != '_none') {
            border_classes += $(this).val() + ' ';
          }
        });

        // Loop through the directions.
        for (var i = 0; i < directions.length; i++) {
          // Border style.
          $('input.bs-field-border-style-' + directions[i]).each(function() {
            if ($(this).is(':checked') && $(this).val() != '_none') {
              border_classes += $(this).val() + ' ';
            }
          });

          // Border width
          border_width_val = $('input.bs-field-border-width-' + directions[i]).val();
          if (border_width_val) {
            border_width_class = border_width['border_' + directions[i] + '_width'][border_width_val];
            if (border_width_class != '_none') {
              border_classes += border_width_class + ' ';
            }
          }

          // Border color.
          $('input.bs-field-border-color-' + directions[i]).each(function() {
            if ($(this).is(':checked') && $(this).val() != '_none') { 
              border_classes += $(this).val() + ' ';
            }
          });
        }

        // Rounded corners
        var rounded_corners_val = $('input.bs-field-rounded-corners').val();
        var rounded_corners_class = rounded_corners.rounded_corners[rounded_corners_val];
        if (rounded_corners_class != '_none') {
          border_classes += rounded_corners_class + ' ';
        }

        // Loop through the corners.
        for (var i = 0; i < corners.length; i++) {
          rounded_corners_val = $('input.bs-field-rounded-corner-' + corners[i]).val();
          if (rounded_corners_val) {
            rounded_corners_class = rounded_corners['rounded_corner_' + corners[i]][rounded_corners_val];
            if (rounded_corners_class != '_none') {
              border_classes += rounded_corners_class + ' ';
            }
          }
        }

        // Remove all classes.
        $('#bs-border-preview').removeClass();
        // Then add the round corner classes.
        $('#bs-border-preview').addClass(border_classes);
      }

      refreshPreviewClasses();

      // Refresh the border classes on change.
      var input_triggers = [
        'input[class^="bs-field-border-style"]',
        'input[class^="bs-field-border-width"]',
        'input[class^="bs-field-border-color"]',
        'input[class^="bs-field-rounded-corner"]'
      ];

      $.each(input_triggers, function (index, value) {
        $(value, context).on('change', function() {
          $(this).parents('.fieldset-wrapper').addClass('style-selected');
          refreshPreviewClasses();
        });
      });
  
    }
  };

})(jQuery, Drupal, once);
