/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

class TextTransformationAdapter {

  static get pluginName() {
    return 'TextTransformationAdapter';
  }

  constructor( editor ) {
    this.editor = editor;
    const regexExpressions = this.editor.config._config.typing?.transformations?.drupal_config?.regex;
    if (!regexExpressions || regexExpressions.length === 0) {
      return;
    }
    let extraRegex = [];
    for (const regex of regexExpressions) {
      let regObj = {};
      let regexFormula = new RegExp(regex.from);
      regObj.from = regexFormula;
      regObj.to = [];
      for (let toElement of regex.to) {
        if (toElement === "null") {
          regObj.to.push(null);
        } else {
          regObj.to.push(toElement);
        }
      }
      extraRegex.push(regObj);
    }
    if (this.editor.config._config.typing.transformations.extra) {
      this.editor.config._config.typing.transformations.extra = this.editor.config._config.typing.transformations.extra.concat(extraRegex)
    } else {
      this.editor.config._config.typing.transformations.extra = extraRegex;
    }

  }
}

export default TextTransformationAdapter;
