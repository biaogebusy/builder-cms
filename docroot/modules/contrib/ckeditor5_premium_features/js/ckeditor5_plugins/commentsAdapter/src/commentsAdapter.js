
/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

import CollaborationStorage
  from "../../collaborationStorage/src/collaborationStorage.js";

class CommentsAdapter {
  constructor( editor ) {
    this.editor = editor;
    this.storage = new CollaborationStorage(editor);

    const extraCommentsPlugins = Array.from(this.editor.plugins._availablePlugins.values()).filter(
        plugin => [ 'Bold', 'Italic', 'List', 'Autoformat' ].includes( plugin.pluginName ),
    );

    this.editor.config._config.comments.editorConfig.extraPlugins.push(...extraCommentsPlugins);
  }

  static get pluginName() {
    return 'CommentsAdapter'
  }

  static get requires() {
    return [ 'CommentsRepository', 'Bold', 'Italic', 'List', 'Autoformat' ];
  }

  init() {
    if (this.storage.processCollaborationCommandDisable("addCommentThread")) {
      return;
    }

    if (!this.editor.plugins.has('CommentsRepository')) {
      return
    }

    const commentsRepositoryPlugin = this.editor.plugins.get( 'CommentsRepository' );
    const isRealtimeCommentsEnabled = this.editor.plugins.has('RealTimeCollaborativeComments');
    const commentsRepositoryElement = document.querySelector(this.storage.getSourceDataSelector('comments'));
    const isRealtimeCollboration = this.editor.plugins.has( 'RealtimeAdapter' );

    if (!commentsRepositoryElement || commentsRepositoryElement.value == '' || isRealtimeCommentsEnabled) {
      return;
    }

    // Load comments (only in non realtime collaboration mode).
    if (!isRealtimeCollboration) {
      const threads = JSON.parse(commentsRepositoryElement.value);
      for (const thread of threads) {
        commentsRepositoryPlugin.addCommentThread(thread);
      }
    }

    // Observe data change and update the data fields.
    this.editor.model.document.on( 'comments:change:data', () => {
      this.updateStorage(commentsRepositoryPlugin, commentsRepositoryElement);
    });

    this.editor.model.document.on( 'trackchanges:change:data', () => {
      this.updateStorage(commentsRepositoryPlugin, commentsRepositoryElement);
    });

    const events = [
      'addComment',
      'change',
      'removeComment',
      'removeCommentThread',
      'updateComment',
      'resolveCommentThread',
      'reopenCommentThread'
    ];

    for (const event of events) {
      commentsRepositoryPlugin.on(event, () => {
        this.editor.model.document.fire('comments:change:data');
      });
    }

    // Hook to form submit.
    const form = this.editor.sourceElement.closest('form');
    form.addEventListener("submit", () => {
      this.updateStorage(commentsRepositoryPlugin, commentsRepositoryElement);
    });
  }

  updateStorage(plugin, storageElement) {
    storageElement.value = JSON.stringify(plugin.getCommentThreads({
      skipNotAttached: false,
      skipEmpty: true,
      toJSON: true
    }));
  }
}

export default CommentsAdapter;
