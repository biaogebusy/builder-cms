/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

class MentionsIntegration {
  constructor( editor ) {
    this.editor = editor;

    if (typeof this.editor.plugins._availablePlugins == 'undefined' ||
      !this.editor.plugins._availablePlugins.has('Mention') ||
      typeof drupalSettings.ckeditor5Premium == 'undefined' ||
      typeof drupalSettings.ckeditor5Premium.mentions == "undefined") {
      return;
    }

    const mentionConfig = {
      feed: this.getFeedItems,
      marker: drupalSettings.ckeditor5Premium.mentions.marker,
      minimumCharacters: drupalSettings.ckeditor5Premium.mentions.minCharacter,
      dropdownLimit: drupalSettings.ckeditor5Premium.mentions.dropdownLimit
    }
    if (this.editor.config._config.mention.feeds.length !== 0) {
      this.editor.config._config.mention.feeds.push(mentionConfig);
    }
    else {
      this.editor.config._config.mention.feeds = [mentionConfig];
    }

    if (typeof this.editor.config._config.comments != "undefined" &&
      typeof this.editor.config._config.comments.editorConfig != "undefined") {
      this.editor.config._config.comments.editorConfig.extraPlugins.push(this.editor.plugins._availablePlugins.get('Mention'));
      this.editor.config._config.comments.editorConfig.mention = {feeds: [mentionConfig]}
    }
  }

  static get pluginName() {
    return 'MentionsIntegration'
  }

  /**
   * Query API endpoint to collect matching users.
   *
   * @param queryText
   *   Username phrase.
   *
   * @returns {Promise<unknown>}
   */
  getFeedItems(queryText) {
    if (typeof drupalSettings.ckeditor5Premium == "undefined" ||
      typeof drupalSettings.ckeditor5Premium.mentions == "undefined") {
      return;
    }

    return new Promise( resolve => {
      jQuery.ajax('/ck5/api/annotations', {
        data: {
          query: queryText,
        },
        success: function(result) {
          resolve( result );
        }
      });
    } );
  }
}

export default MentionsIntegration;
