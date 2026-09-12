/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

class HtmlEmbedAdapter {

  static get pluginName() {
    return 'HtmlEmbedAdapter';
  }

  constructor( editor ) {
    this.editor = editor;
  }

  init() {
    const config = this.editor.config._config.htmlEmbed;
    if (!config || !config.showPreviews || !config.enablePurify) {
      return;
    }
    if (typeof DOMPurify === 'undefined') {
      console.warn('DOMPurify is not loaded. Please include DOMPurify in your page to enable HTML sanitization.');
      return;
    }

    this.editor.config._config.htmlEmbed.sanitizeHtml = ( inputHtml ) => {
      const safe = DOMPurify.sanitize(inputHtml, {USE_PROFILES: {html: true}});
      return { html: safe, hasChanged: safe !== inputHtml };
    }

  }
}

export default HtmlEmbedAdapter;
