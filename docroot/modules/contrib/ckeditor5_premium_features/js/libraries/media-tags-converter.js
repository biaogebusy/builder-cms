/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

(function ($, Drupal) {
  Drupal.CKEditor5PremiumFeatures.mediaTagsConverter = {
    /**
     * Convert drupal-media tags to proper HTML rendered content.
     * @param content
     *   Document content.
     * @param format
     *   Text format.
     *
     * @returns {Promise<string>}
     */
    async convertMediaTags(content, format) {
      return new Promise( async resolve => {
        const parser = new DOMParser();
        const documentDom = parser.parseFromString( content, 'text/html' ).body;
        documentDom.innerHTML = content;

        if (drupalSettings.ckeditor5Premium.isMediaInstalled) {
          let elementsAttributes = this.getTagProperties(documentDom);
          let mediaPaths = await this.queryMediaPaths(elementsAttributes, format);

          this.replaceMediaTags(documentDom, mediaPaths);
        }
        resolve(documentDom.innerHTML);
      });
    },

    /**
     * Returns a list of properties collected for drupal-media tags.
     *
     * @param documentDom
     *   A DOM element to search in.
     *
     * @returns {*[]}
     */
    getTagProperties(documentDom) {
      let elements = documentDom.getElementsByTagName("drupal-media");
      let elementsAttributes = [];

      for (let e = 0; e < elements.length; ++e) {
        let type = elements[e].dataset.entityType;
        let id = elements[e].dataset.entityUuid;

        elementsAttributes.push({type: type, id: id});
      }

      return elementsAttributes;
    },

    /**
     * Queries a backed API to get media elements rendered.
     *
     * @param elementAttributes
     *   List of media tag attributes.
     * @param format
     *   Text editor format.
     *
     * @returns {Promise<unknown>}
     */
    queryMediaPaths(elementAttributes, format) {
      return new Promise( resolve => {
        $.post('/ck5/api/media-tags/' + format, {
          media: JSON.stringify(elementAttributes),
        }).done(function(result) {
          resolve(result);
        });
      });
    },

    /**
     * Replaces media tags with rendered entities HTML.
     *
     * @param documentElements
     *   A document DOM element to search in and replace media tags.
     * @param mediaTagContent
     *   A list of rendered media tags.
     */
    replaceMediaTags(documentElements, mediaTagContent) {
      for (const mediaInfo of mediaTagContent) {
        let mediaTags = documentElements.querySelectorAll('[data-entity-uuid="' + mediaInfo.uuid + '"]');

        for (const tag of mediaTags) {
          tag.innerHTML = mediaInfo.rendered;
        }
      }
    },
  }
})(jQuery, Drupal);
