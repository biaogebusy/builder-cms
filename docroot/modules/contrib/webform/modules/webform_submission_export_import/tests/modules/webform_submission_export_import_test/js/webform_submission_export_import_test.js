/**
 * @file
 * JavaScript behaviors for Webform Export/Import Test module.
 */

(function ($, Drupal, once) {
  /**
   * Set import URL and submit the form.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.webformSubmissionExportImportTest = {
    attach(context) {
      $(once('webform-export-import-test', '#edit-import-url--description a', context))
        .on('click', function () {
          $('#edit-import-url').val(this.href);
          $('#webform-submission-export-import-upload-form').trigger('submit');
          return false;
        });
    }
  };

})(jQuery, Drupal, once);
