import { Plugin } from 'ckeditor5/src/core';
import CKEditorTemplatesUI from './ckeditorTemplatesUI';
import CKEditorTemplatesEditing from './ckeditorTemplatesEditing';

/**
 * Generates a CKEditor 5 plugin.
 */
export default class CKEditorTemplates extends Plugin {

  /**
   * @inheritdoc
   */
  static get requires() {
    return [
      CKEditorTemplatesUI,
      CKEditorTemplatesEditing
    ];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'ckeditorTemplates';
  }
}
