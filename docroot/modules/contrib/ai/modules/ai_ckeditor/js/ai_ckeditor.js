/**
 * @file
 * Extends functionality for supporting CKEditor 5 AI plugins.
 */

((Drupal, debounce, CKEditor5, $, once) => {

  /**
   * Function to handle background requests for editor streaming.
   *
   * @param ajax
   *   The AJAX object.
   * @param parameters
   *   The parameters from AiRequestCommand.
   */
  Drupal.AjaxCommands.prototype.aiRequest = function (ajax, parameters) {
    const editor_id = $('#ai-ckeditor-response textarea').attr('data-ckeditor5-id');
    const editor = Drupal.CKEditor5Instances.get(editor_id);
    editor.execute('AiWriter', parameters);
  };

  /**
   * Public API for AI CKEditor integration.
   *
   * @namespace
   */
  Drupal.aickeditor = {
    /**
     * Open a dialog for a Drupal-based plugin.
     *
     * This dynamically loads jQuery UI (if necessary) using the Drupal AJAX
     * framework, then opens a dialog at the specified Drupal path.
     *
     * @param {string} url
     *   The URL that contains the contents of the dialog.
     * @param {function} saveCallback
     *   A function to be called upon saving the dialog.
     * @param {object} dialogSettings
     *   An object containing settings to be passed to the jQuery UI.
     * @param {object} additionalData
     *   An object containing form data to be passed to the plugin.
     */
    openDialog(url, saveCallback, dialogSettings, additionalData) {
      // Add a consistent dialog class.
      const classes = dialogSettings.dialogClass
        ? dialogSettings.dialogClass.split(' ')
        : [];
      classes.push('ui-dialog--narrow');
      dialogSettings.dialogClass = classes.join(' ');

      if (typeof dialogSettings.autoResize !== 'undefined') {
        if (typeof dialogSettings.autoResize === 'string') {
          dialogSettings.autoResize = window.matchMedia('(' + dialogSettings.autoResize + ')').matches;
        }
      }

      dialogSettings.height = dialogSettings.height
        ? dialogSettings.height
        : (dialogSettings.height = 'auto');

      dialogSettings.width = dialogSettings.width
        ? dialogSettings.width
        : (dialogSettings.width = 'auto');

      const ckeditorAjaxDialog = Drupal.ajax({
        dialog: dialogSettings,
        dialogType: 'modal',
        selector: '.ckeditor5-dialog-loading-link',
        url,
        progress: {type: 'fullscreen'},
        submit: {
          editor_object: {},
          ...additionalData,
        },
      });
      ckeditorAjaxDialog.execute();

      // Store the save callback to be executed when this dialog is closed.
      Drupal.ckeditor5.saveCallback = saveCallback;
    },
  };
})(Drupal, Drupal.debounce, CKEditor5, jQuery, once);
