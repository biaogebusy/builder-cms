import { Plugin } from 'ckeditor5/src/core';
import { Widget } from 'ckeditor5/src/widget';
import CKEditorTemplatesCommand from './ckeditorTemplatesCommand';

/**
 * Handles the plugin functionality.
 */
export default class CKEditorTemplatesEditing extends Plugin {

  /**
   * @inheritdoc
   */
  static get requires() {
    return [Widget];
  }

  /**
   * @inheritdoc
   */
  init() {
    this.editor.commands.add(
      'ckeditorTemplates',
      new CKEditorTemplatesCommand(this.editor)
    );
  }

}
