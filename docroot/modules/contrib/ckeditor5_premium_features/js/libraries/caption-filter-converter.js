/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

(function ($, Drupal) {
  Drupal.CKEditor5PremiumFeatures.captionFilterConverter = {
    /**
     * Apply caption filter to content.
     * @param content
     *   Document content.
     *
     * @returns {Promise<string>}
     */
    async applyCaptionFilter(content) {
      return new Promise(async resolve => {
        let result = await new Promise(resolve => {
          $.post('/ck5/api/caption-filter', {
            content: content
          }).done(function(result) {
            if (!result.content) {
              resolve(content);
            }
            resolve(result.content);
          }).fail(function() {
            resolve(content);
          });
        });
        resolve(result);
      });
    },
  }
})(jQuery, Drupal);
