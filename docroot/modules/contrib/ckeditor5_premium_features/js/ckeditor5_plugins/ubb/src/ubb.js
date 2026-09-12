/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

class Ubb {
  constructor(editor) {
    this.editor = editor;
  }

  init() {
    const editor = this.editor;

    if (this.editor.config._config.licenseKey === "GPL") {
      return;
    }

    if (typeof this.editor.sourceElement === "undefined") {
      return;
    }

    const format = this.editor.sourceElement.dataset.editorActiveTextFormat
    const INTEGRATION_NAME = 'drupal';
    let INTEGRATION_USAGE_DATA = {
      version: '1.8.3',
    };

    if (typeof format === "undefined") {
      INTEGRATION_USAGE_DATA.frameworkVersion = "n/a";
    }
    else {
      var xhttp = new XMLHttpRequest();
      xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          try {
            const json = JSON.parse(xhttp.responseText);
            INTEGRATION_USAGE_DATA.frameworkVersion = json.version ? json.version : 'n/a';
          }
          catch (e) {
            INTEGRATION_USAGE_DATA.frameworkVersion = 'n/a';
          }
        }
      };
      xhttp.open("GET", "/ckeditor5-premium-features/drupal-version/" + format + "/", false);
      xhttp.send();
    }

    editor.on( 'collectUsageData', ( source, { setUsageData } ) => {
      setUsageData( `integration.${ INTEGRATION_NAME }`, INTEGRATION_USAGE_DATA );
    });
  }

}

export default Ubb;
