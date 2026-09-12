/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

import SidebarAdapter from "../../sidebarAdapter/src/sidebarAdapter.js";
import CollaborationStorage
  from "../../collaborationStorage/src/collaborationStorage.js";

class ToolbarAdapter {
  constructor(editor) {
    this.editor = editor;
    this.storage = new CollaborationStorage(editor);
  }

  afterInit() {
    if (typeof this.editor.sourceElement === "undefined") {
      return;
    }
    this.editor.on('ready', () => {
      this.toolbarContainer = this.getToolbarElement(this.editor.sourceElement.id);
      if (this.toolbarContainer) {
        this.toolbarContainer.appendChild(this.editor.ui.view.toolbar.element);
      }
    });
  }

  destroy() {
    if (this.toolbarContainer) {
      this.toolbarContainer.innerHTML = "";
    }
  }

  getToolbarElement(elementId) {
    let editorParent = this.storage.getEditorParentContainer(elementId);

    if (!editorParent) {
      return null;
    }

    return editorParent.querySelector('.ck-toolbar-container');
  }
}

export default ToolbarAdapter;
