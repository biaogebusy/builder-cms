/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

(function ($, Drupal) {
  Drupal.CKEditor5PremiumFeatures.relativePathsProcessor = function(content) {
    let basePath = window.location.origin + drupalSettings.path.baseUrl;

    let attributes = [
      'src',
      'href',
      'poster',
      'icon',
      'data',
      'background'
    ];
    let pattern =  new RegExp("(" + attributes.join("|") + ")\\s*=\\s*(\"|')(((?!\/\/)[^\"'><])+)(\"|')", "igd");

    content = content.replace(pattern, function(matched){
      // Let's make sure there is no additional spaces around "=" and '"' characters.
      matched = matched.replace(/\s*\=\s*\"\s*\/?/g,'="');

      let splitted = matched.split('="');

      // At this point we're sure that basePath is a string with "/" at the end,
      // and that there is no "/" at the beginning of URL path stored in splitted[1]
      splitted[1] = basePath + splitted[1];

      return splitted.join('="');
    })

    return '<base href="' + basePath + '" />'
      + content;
  }
}) (jQuery, Drupal);
