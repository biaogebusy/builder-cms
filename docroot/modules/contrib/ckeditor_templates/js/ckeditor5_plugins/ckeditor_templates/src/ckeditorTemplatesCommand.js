import { Command } from 'ckeditor5/src/core';

/**
 * Command for injecting code into the CKEditor.
 */
export default class CKEditorTemplatesCommand extends Command {
  /**
   * @inheritdoc
   */
  refresh() {
    // The command can be executed whenever the editor is not in read-only mode.
    this.isEnabled = !this.editor.isReadOnly;
  }

  /**
   * @inheritdoc
   */
  execute(htmlCode, replace) {
    const editor = this.editor;
    editor.model.change(writer => {
      if (replace) {
        editor.data.set(htmlCode);
      } else {
        const viewFragment = editor.data.processor.toView(htmlCode);
        const modelFragment = editor.data.toModel(viewFragment);
        editor.model.insertContent(modelFragment);
      }
    });
  }
}
