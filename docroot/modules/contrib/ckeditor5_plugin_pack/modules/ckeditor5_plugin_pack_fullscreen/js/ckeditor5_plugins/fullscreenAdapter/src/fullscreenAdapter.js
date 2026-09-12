/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

import { Plugin } from 'ckeditor5/src/core';

export default class FullscreenAdapter extends Plugin {

  static get pluginName() {
    return 'fullscreenAdapter'
  }

  constructor(editor) {
    super(editor);
    this.editor = editor;
    this.editor.config.define('fullscreen.onEnterCallback', this.enterCallback.bind(this));
  }

  enterCallback(container) {
    if (this.editor.plugins.has('WordCount')) {
      const wordCount = this.editor.plugins.get('WordCount');
      const fullScreenCommand = this.editor.commands.get('toggleFullscreen');
      const handler = fullScreenCommand.fullscreenHandler;
      handler.moveToFullscreen(wordCount.wordCountContainer, "body-wrapper");
    }

  }
}
