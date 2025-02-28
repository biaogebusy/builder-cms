/**
 * @file
 * Behaviors Bootstrap Layout Builder general scripts.
 */

(function ($, Drupal, once) {
  "use strict";

  // Configure Section.
  Drupal.behaviors.bootstrapLayoutBuilderConfigureSection = {
    attach: function (context) {
      // Custom solution for Bootstrap 3 Drupal theme.
      $('input.blb_container_type', context).each(function() {
        var checked = $(this).prop("checked");
        if (typeof checked !== typeof undefined && checked !== false) {
          $(this).parent('label').addClass('active');
        }
      });

      // Custom solution for Bootstrap 3 & Bario Drupal themes.
      $('.blb_container_type .fieldset-wrapper label', context).on('click', function () {
        $(this).parents('.fieldset-wrapper').find('label').removeClass('active');
        $(this).parents('.fieldset-wrapper').find('input').prop("checked", false);
        // Temp comment the following line because of conflict with live preview.
        // $(this).parent().find('input').prop('checked', true);
        $(this).addClass('active');
      });

      // Graphical Layout Columns
      $('.blb_breakpoint_cols', context).each(function () {
        const numOfCols = 12;
        // .custom-control, .custom-radio to solve Bario issues.
        once('custom-control-custom-radio-bario',$(this).find('.form-item, .custom-control, .custom-radio'),context).forEach(function (elem,i) {
          var cols = $(elem).find('input').val().replace('blb_col_', '');
          var colsConfig = cols.split('_');
          var colsLabel = $(elem).find('label');
          var col_classes = 'blb_breakpoint_col';
          var checked = $(elem).find('input').prop("checked");
          if (typeof checked !== typeof undefined && checked !== false) {
            col_classes += ' bp-selected';
          }

          // Wrap our radio labels and display as a tooltip.
          colsLabel.wrapInner('<div class="bs_tooltip bs_tooltip-lg"></div>');

          // Provide a graphical representation of the columns via some nifty divs styling.
          $.each(colsConfig, function(index, value) {
            var width = ((value / numOfCols) * 100);
            $('<div />', {
              'text': width.toFixed(0) + '%',
              'style': 'width:' + width + '%;',
              'class': col_classes,
            })
            .appendTo(colsLabel)
            .on('click', function () {
              $(this).parents('.blb_breakpoint_cols').find('.blb_breakpoint_col').removeClass('bp-selected');
              $(this).parents('.blb_breakpoint_cols').find('input').prop("checked", false);
              $(this).parents('label').parent().find('input').prop("checked", true);
              $(this).parents('label').find('.blb_breakpoint_col').addClass('bp-selected');
            });

          });
        });

      });

      // Auto-sized textareas.
      $('textarea.blb-auto-size', context).each(function() {
        this.setAttribute('style', 'height:' + (this.scrollHeight) + 'px;overflow-y:hidden;min-height:60px!important;padding:.65rem 1rem;');
      });

    }
  };

})(jQuery, Drupal, once);
