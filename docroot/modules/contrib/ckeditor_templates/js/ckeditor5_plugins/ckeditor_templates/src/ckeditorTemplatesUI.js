import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import CKEditorTemplatesIcon from '../theme/icons/templates.svg';

/**
 * Generates a toolbar button.
 */
export default class EmbeddedContentUI extends Plugin {

  /**
   * @inheritdoc
   */
  init() {
    const editor = this.editor;
    // Gets the options from the Drupal plugin.
    const dialogOptions = this.editor.config.get('ckeditorTemplates');
    if (!dialogOptions || !dialogOptions.openDialog || !dialogOptions.dialogSettings || !dialogOptions.dialogUrl) {
      return;
    }

    // Adds a button to the CKEditor toolbar.
    editor.ui.componentFactory.add('ckeditorTemplates', (locale) => {

      // Generates a button view.
      const command = editor.commands.get('ckeditorTemplates');
      const buttonView = new ButtonView(locale);
      buttonView.set({
        label: Drupal.t('Templates'),
        icon: CKEditorTemplatesIcon,
        tooltip: true
      });

      // Adds a command object to the button.
      buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');

      // Adds a listener to the button click.
      this.listenTo(buttonView, 'execute', () => {
        const url = dialogOptions.dialogUrl;
        const settings = dialogOptions.dialogSettings;
        const callback = ({ htmlCode, replace }) => {
          editor.execute('ckeditorTemplates', htmlCode, replace);
        };

        dialogOptions.openDialog(url, callback, settings);
      });

      return buttonView;
    });
  }
}
