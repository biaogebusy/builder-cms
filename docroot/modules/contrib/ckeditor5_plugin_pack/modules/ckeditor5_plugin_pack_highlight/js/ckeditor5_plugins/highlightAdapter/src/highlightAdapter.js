/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

class HighlightAdapter {

  static get pluginName() {
    return 'HighlightAdapter';
  }

  constructor( editor ) {
    this.editor = editor;
  }

  init() {
    const highlightOptions = this.editor.config._config.highlight.options;
    for (const option of highlightOptions) {
      if (option.class.startsWith('custom-highlight')) {
        const customClass = option.class;
        const customColor = option.color;
        const customType = option.type;

        const backgroundColorVar = 'background-color';
        const colorVar = 'color';

        const style = document.createElement('style');
        if (customType === 'pen') {
          style.innerHTML = `.${customClass} { ${colorVar}: ${customColor}; ${backgroundColorVar}: transparent}`;
        } else {
          style.innerHTML = `.${customClass} { ${backgroundColorVar}: ${customColor};}`;
        }
        document.head.appendChild(style);
      }
    }

  }
}

export default HighlightAdapter;
