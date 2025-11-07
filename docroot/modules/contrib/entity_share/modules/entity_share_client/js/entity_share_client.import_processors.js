/**
 * @file
 * Attaches show/hide functionality to checkboxes in the import config form.
 */

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.entityShareClientImportProcessor = {
    attach: function (context, settings) {
      $('.entity-share-client-status-wrapper input.form-checkbox', context).each(function () {
        var $checkbox = $(this);
        var processor_id = $checkbox.data('id');

        var $rows = $('.entity-share-client-processor-weight--' + processor_id, context);
        var tab = $('.entity-share-client-processor-settings-' + processor_id, context).data('verticalTab');

        // Bind a click handler to this checkbox to conditionally show and hide
        // the processor's table row and vertical tab pane.
        $checkbox.on('click.entityShareClientUpdate', function () {
          if ($checkbox.is(':checked')) {
            $rows.show();
            if (tab) {
              tab.tabShow().updateSummary();
            }
          }
          else {
            $rows.hide();
            if (tab) {
              tab.tabHide().updateSummary();
            }
          }
        });

        // Attach summary for configurable items (only for screen-readers).
        if (tab) {
          tab.details.drupalSetSummary(function () {
            return $checkbox.is(':checked') ? Drupal.t('Enabled') : Drupal.t('Disabled');
          });
        }

        // Trigger our bound click handler to update elements to initial state.
        $checkbox.triggerHandler('click.entityShareClientUpdate');
      });
    }
  };

})(jQuery, Drupal);
