/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

class WProofreaderAdapter {
  constructor( editor ) {
    this.editor = editor;
  }

  afterInit() {
    const serviceType = this.editor.config._config.wproofreader.cke5.serviceType;
    if (serviceType === 'on_premise') {
      return;
    }
    this._validatePermission();
    this._validateKey();
  }

  _validateKey() {
    Drupal.CKEditor5PremiumFeaturesWProofreader.validate().then((response) => {
      if (!response.valid) {
        this._disablePlugin();
        let errorMessage = 'wproofreader-service-id-error';
        if (response.usage_limit_error) {
          errorMessage = 'wproofreader-usage-limit-error';
        }
        this._dispatchErrorEvent(errorMessage)
      }
    });
  }

  _validatePermission() {
    const isUserHasPermission = this.editor.config._config.wproofreader.cke5?.validPermission;
    if (!isUserHasPermission) {
      this._disablePlugin();
      this._dispatchErrorEvent('wproofreader-permission-error')
    }
  }

  _disablePlugin() {
    this.editor.plugins.get('WProofreader').forceDisabled('load-error')
    this.editor.commands.get('WProofreaderToggle').forceDisabled('load-error')
    this.editor.commands.get('WProofreaderSettings').forceDisabled('load-error')
    this.editor.commands.get('WProofreaderDialog').forceDisabled('load-error')
  }

  _dispatchErrorEvent(errorMessage) {
    var error = new ErrorEvent('error', {
      error : new Error(errorMessage),
    });
    dispatchEvent(error)
  }
}

export {
  WProofreaderAdapter
};


(function ($, Drupal) {
  Drupal.CKEditor5PremiumFeaturesWProofreader = {

    /**
     * Validate if service id is valid.
     *
     * @returns {Promise<string>}
     */
    validate() {
      return new Promise( resolve => {
        $.get('/ckeditor5-premium-features-wproofreader/validate-service-id')
          .done(function(result) {
            resolve({
              valid: result.valid,
            });
          }).catch((error)=> {
            let response = error.responseJSON;
            resolve({
              valid: response.valid,
              usage_limit_error: response.usage_limit_error ?? null
            });
        });
      });
    },
  }
})(jQuery, Drupal);
