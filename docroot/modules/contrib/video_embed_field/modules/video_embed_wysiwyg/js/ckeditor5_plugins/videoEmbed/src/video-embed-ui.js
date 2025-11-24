/**
 * @file registers the videoEmbed toolbar button and binds functionality to it.
 */

import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';

/* @todo Choose the best icon and remove others. */
import icon from '../../../../icons/play-circle.svg';

export default class VideoEmbedUi extends Plugin {
  init() {
    const editor = this.editor;

    // This will register the videoEmbed toolbar button.
    editor.ui.componentFactory.add('videoEmbed', (locale) => {
      const command = editor.commands.get('insertVideoEmbed');
      const buttonView = new ButtonView(locale);

      // Create the toolbar button.
      buttonView.set({
        label: editor.t('Video Embed'),
        icon,
        tooltip: true,
      });

      // Bind the state of the button to the command.
      buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');

      // Execute the command when the button is clicked (executed).
      this.listenTo(buttonView, 'execute', () => {
        this.openEditingDialog();
      });

      return buttonView;
    });
  }

  /**
   * Opens video embed form when the editing button is clicked.
   */
  openEditingDialog() {
    const { editor } = this;

    // If the selected element while we click the button is an instance
    // of the video_embed widget, extract its values so they can be
    // sent to the server to prime the configuration form.
    const existingValues = { settings: {} };
    const selectedVideoEmbedElement =
      editor.model.document.selection.getSelectedElement();
    if (selectedVideoEmbedElement) {
      if (selectedVideoEmbedElement.hasAttribute('videoUrl')) {
        existingValues.video_url =
          selectedVideoEmbedElement.getAttribute('videoUrl');
      }
      [
        'responsive',
        'width',
        'height',
        'autoplay',
        'title_format',
        'title_fallback',
      ].forEach(function (attributeName) {
        if (selectedVideoEmbedElement.hasAttribute(attributeName)) {
          existingValues.settings[attributeName] =
            selectedVideoEmbedElement.getAttribute(attributeName);
        }
      });
    }
    this.constructor._openDialog(
      Drupal.url(
        `video-embed-wysiwyg/dialog/${editor.config.get('videoEmbed').format}`,
      ),
      existingValues,
      (newValues) => {
        const attributes = {
          videoUrl: newValues.video_url,
          responsive: newValues.settings.responsive,
          width: newValues.settings.width,
          height: newValues.settings.height,
          title_format: newValues.settings.title_format,
          title_fallback: newValues.settings.title_fallback,
          autoplay: newValues.settings.autoplay,
          // These attributes are useful only for editor preview, but are
          // kept on dataDowncast so that they can be retrieved on later
          // upcast+editingDowncast.
          settingsSummary: newValues.settings_summary[0],
          previewThumbnail: newValues.preview_thumbnail,
        };
        editor.execute('insertVideoEmbed', attributes);
      },
      {
        title: Drupal.t('Video Embed'),
        dialogClass: 'video-embed-dialog',
      },
    );
  }

  /**
   * This method is adapted from drupal's ckeditor5.js file due to an issue
   * where the "editor_object" isn't passed to the ajax request.
   *
   * See https://www.drupal.org/project/drupal/issues/3303191
   *
   * @param {string} url
   *   The URL that contains the contents of the dialog.
   * @param {object} existingValues
   *   Existing values that will be sent via POST to the url for the dialog
   *   contents.
   * @param {function} saveCallback
   *   A function to be called upon saving the dialog.
   * @param {object} dialogSettings
   *   An object containing settings to be passed to the jQuery UI.
   */
  static _openDialog(url, existingValues, saveCallback, dialogSettings = {}) {
    // Add a consistent dialog class.
    const classes = dialogSettings.dialogClass
      ? dialogSettings.dialogClass.split(' ')
      : [];
    classes.push('ui-dialog--narrow');
    dialogSettings.dialogClass = classes.join(' ');
    dialogSettings.autoResize = window.matchMedia('(min-width: 600px)').matches;
    dialogSettings.width = 'auto';

    const ckeditorAjaxDialog = Drupal.ajax({
      dialog: dialogSettings,
      dialogType: 'modal',
      selector: '.ckeditor5-dialog-loading-link',
      url,
      progress: { type: 'fullscreen' },
      submit: {
        editor_object: existingValues,
      },
    });
    ckeditorAjaxDialog.execute();

    // Store the save callback to be executed when this dialog is closed.
    Drupal.ckeditor5.saveCallback = saveCallback;
  }
}
