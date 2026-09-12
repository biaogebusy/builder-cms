/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

import { Plugin } from 'ckeditor5/src/core';

export default class DrupalPoweredBy extends Plugin {

  static get pluginName() {
    return 'drupalPoweredBy'
  }

  init() {
    const editor = this.editor;
    if (editor.config._config.drupalPoweredBy) {

      if (!editor.config._config.ui) {
        editor.config._config.ui = {
          poweredBy: {
            forceVisible: true
          }
        }
      } else {
        editor.config._config.ui.poweredBy = {
          forceVisible: true
        }
      }
    }
  }
}
