/**
 * @file
 * dropzonejs_eb_widget.common.js
 *
 * Bundles various dropzone eb widget behaviours.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.dropzonejsPostIntegrationMediaLibrary = {
    attach: function (context) {
      if (typeof drupalSettings.dropzonejs.instances !== 'undefined') {
        Object.values(drupalSettings.dropzonejs.instances).forEach( function (item) {
          if (typeof item.instance !== 'undefined') {

            var $form = $(item.instance.element).parents('form');

            item.instance.on('queuecomplete', function () {
              var dzInstance = item.instance;
              var filesInQueue = dzInstance.getQueuedFiles();
              var acceptedFiles;
              var i;

              if (filesInQueue.length === 0) {
                acceptedFiles = dzInstance.getAcceptedFiles();

                // Ensure that there are some files that should be submitted.
                if (acceptedFiles.length > 0 && dzInstance.getUploadingFiles().length === 0) {
                  // First submit accepted files and clear them from list of
                  // dropped files afterwards.
                  $form.find('[id="auto_select_handler"]')
                    .trigger('auto_select_media_library_widget');

                  // Remove accepted files -> because they are submitted.
                  for (i = 0; i < acceptedFiles.length; i++) {
                    dzInstance.removeFile(acceptedFiles[i]);
                  }
                }
              }
            });
          }
        });
      }
    }
  };

}(jQuery, Drupal, drupalSettings));
