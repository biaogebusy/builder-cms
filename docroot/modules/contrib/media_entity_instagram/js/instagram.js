/**
 * @file
 */

(function (Drupal) {
  "use strict";

  Drupal.behaviors.instagramMediaEntity = {
    attach: function (context) {
      function _init() {
        instgrm.Embeds.process();
      }

      //TODO: ckeditor integration still doesn't work
      // If the instagram card is being embedded in a CKEditor's iFrame the widgets
      // library might not have been loaded yet.
      if (typeof instgrm == 'undefined') {
        var script = document.createElement("script");
        script.src = '//platform.instagram.com/en_US/embeds.js';
        document.head.appendChild(script);
      }
      _init();
    }
  };

})(Drupal);
