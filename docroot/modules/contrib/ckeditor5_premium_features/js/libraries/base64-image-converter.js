/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

(function ($, Drupal) {
  Drupal.CKEditor5PremiumFeatures.base64ImageConverter = {

    /**
     * Convert images url into base64 in HTML
     * @param content
     *   Document content.
     * @param filesType
     *   Type of conversion. Private or All files.
     *
     * @returns {Promise<string>}
     */
    async convert(content, filesType) {
      return new Promise( async resolve => {
        let result = await new Promise( resolve => {
          $.post('/ck5/api/base64-image-converter', {
            document: JSON.stringify(content),
            filesType: filesType,
          }).done(function(result) {
            if(!result.document) {
              resolve(content);
            }
            resolve(result.document);
          });
        });
        resolve(result);
      });
    },
  }
})(jQuery, Drupal);
