import { Command } from 'ckeditor5/src/core';
import AiNetworkStatus from "../Utility/AiNetworkStatus";

export default class AiWriter extends Command {

  constructor(editor) {
    super(editor);
    this._status = this.editor.plugins.get(AiNetworkStatus);
  }

  /**
   * Handles the execution and response for writing into a CKEditor 5 instance.
   *
   * @param request_parameters
   */
  execute(request_parameters) {
    const status = this._status;

    status.fire('ai_status', {
      status: 'Waiting for response...'
    });

    const editor = this.editor;
    const sourceEditing = editor.plugins.get('SourceEditing');
    editor.enableReadOnlyMode('ai_ckeditor');
    sourceEditing.set("isSourceEditingMode", true);
    sourceEditing.isEnabled = false;

    // Locate the target field (sourceEditingTextarea or a custom field)
    const sourceEditingTextarea = editor.editing.view.getDomRoot()?.nextSibling?.firstChild;

    // Clear the field before writing new content
    if (sourceEditingTextarea) {
      sourceEditingTextarea.value = ''; // Clear the field
    }

    editor.model.change(async (writer) => {
      const response = await fetch(
        drupalSettings.path.baseUrl +
          "api/ai-ckeditor/request/" +
          request_parameters.editor_id +
          "/" +
          request_parameters.plugin_id,
        {
          method: "POST",
          credentials: "same-origin",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify(request_parameters),
        }
      );

      if (!response.ok) {
        status.fire('ai_status', {
          status: 'An error occurred. Check the logs for details.'
        });

        setTimeout(() => {
          status.fire('ai_status', {status: 'Idle'});
        }, 3000);
      }

      status.fire('ai_status', {
        status: 'Receiving response...'
      });

      const reader = response.body.getReader();

      while (true) {
        const {value, done} = await reader.read();
        const text = new TextDecoder().decode(value);

        if (done) {
          status.fire('ai_status', {
            status: 'All done!'
          });

          setTimeout(() => {
            status.fire('ai_status', {status: 'Idle'});
          }, 1000);

          break;
        }

        status.fire('ai_status', {
          status: 'Writing...'
        });

        let currentText = sourceEditingTextarea.value;
        sourceEditingTextarea.value = currentText + text;
        editor.setData(sourceEditingTextarea.value);
        sourceEditing.updateEditorData();
      }

      sourceEditing.set("isSourceEditingMode", false);
      sourceEditing.isEnabled = true;
      editor.disableReadOnlyMode('ai_ckeditor');
    });
  }
}
