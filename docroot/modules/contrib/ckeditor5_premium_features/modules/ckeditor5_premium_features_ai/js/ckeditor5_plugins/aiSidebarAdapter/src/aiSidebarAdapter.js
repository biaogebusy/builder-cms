/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

class AiSidebarAdapter {
  constructor( editor ) {
    this.editor = editor;
    if (typeof this.editor.sourceElement === "undefined") {
      return;
    }
    if (this.editor.config._config.ai.container.type === 'sidebar') {
      this.editor.config._config.ai.container.element = document.getElementById( this.editor.sourceElement.id + '-ck-ai-sidebar' );
    }

    if (this.editor.config._config.ai.custom && this.editor.config._config.ai.custom.context) {
      this.editor.config._config.ai.chat.context.sources = [
        {
          id: 'custom-context',
          label: 'Drupal context',
          getResources: async (query) => this.editor.config._config.ai.custom.context,
        }
      ];
    }
  }

  static get pluginName() {
    return 'AiSidebarAdapter'
  }

}

export default AiSidebarAdapter;
