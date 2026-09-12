/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

class AiAdapter {
  constructor( editor ) {
    this.editor = editor;
    if (typeof this.editor.sourceElement === "undefined") {
      return;
    }
    // Set the channelId from drupalSettings
    if (typeof drupalSettings.ckeditor5ChannelId !== "undefined" &&
      typeof drupalSettings.ckeditor5ChannelId[this.editor.sourceElement.dataset.ckeditorfieldid] !== "undefined") {
      this.editor.config._config.collaboration = this.editor.config._config.collaboration || {};
      this.editor.config._config.collaboration.channelId = drupalSettings.ckeditor5ChannelId[this.editor.sourceElement.dataset.ckeditorfieldid];
    }
  }

  static get pluginName() {
    return 'AiAdapter'
  }

  init() {
    if (typeof this.editor.sourceElement === "undefined") {
      return;
    }

    if (typeof drupalSettings.ckeditor5Premium === "undefined" || !this.editor.plugins.has('Users') ) {
      return;
    }

    if (this.editor.plugins.has('RealtimeAdapter')) {
      return;
    }

    const config = this.editor.config.get('ai');
    const usersPlugin = this.editor.plugins.get( 'Users' );

    if (!usersPlugin.users.get(config.drupalUser.id)) {
      usersPlugin.addUser( config.drupalUser );
    }

    if (usersPlugin.me === null) {
      usersPlugin.defineMe( config.drupalUser.id );
    }
  }
}

export default AiAdapter;
