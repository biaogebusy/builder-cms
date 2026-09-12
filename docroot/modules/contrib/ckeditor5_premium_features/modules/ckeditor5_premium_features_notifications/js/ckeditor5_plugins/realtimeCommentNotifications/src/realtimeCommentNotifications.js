/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

class RealtimeCommentNotifications {
  constructor( editor ) {
    this.editor = editor;

  }

  static get pluginName() {
    return 'RealtimeCommentNotifications'
  }


  init() {
    if (!this.editor.plugins.has('CommentsRepository')) {
      return
    }
    this.commentsRepositoryPlugin = this.editor.plugins.get( 'CommentsRepository' );
    this.channelId = this.editor.config._config.collaboration.channelId

    if (this.isEntityNew()) {
      return;
    }
    this.commentsRepositoryPlugin.on('addComment', (evt, data) => {
      setTimeout(() => {
        this.sendNotification(evt, data);
      }, "1000");
    });
  }

  sendNotification(evt, data) {
    if (data.isFromAdapter !== undefined) {
      return;
    }

    const thread = this.commentsRepositoryPlugin.getCommentThread(data.threadId);
    const lastKey = thread.comments._items.length - 1;
    const lastItem = thread.comments._items[lastKey];

    const payload = {
      'comment': lastItem,
      'editor_content': this.editor.getData(),
      'element_key': evt.source.context.sourceElement.dataset.ckeditor5PremiumElementId,
      'channel_id': this.channelId,
      'thread_id': data.threadId,
      'thread': thread
    };

    const xhr = new XMLHttpRequest();
    const url='/ckeditor5-premium-features-realtime-collaboration/realtime-comment-notification/' + this.channelId;

    xhr.open("POST", url);
    xhr.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
    xhr.send(JSON.stringify(payload));
  }

  isEntityNew() {
    const url='/ckeditor5-premium-features-realtime-collaboration/check-channel/' + this.channelId;
    const xhr = new XMLHttpRequest();
    xhr.open("GET", url, false);
    xhr.send();
    return xhr.responseText === "false";
  }

}

export default RealtimeCommentNotifications;
