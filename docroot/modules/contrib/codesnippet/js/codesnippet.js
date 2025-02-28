/**
 * @file
 * Enables syntax highlighting via HighlightJS on the HTML code tag.
 */

(function (Drupal) {
  'use strict';

  Drupal.behaviors.codesnippet = {
    attach: function (context, settings) {
      hljs.initHighlightingOnLoad();
      context.querySelectorAll('pre code').forEach(element => {
        element.style.overflowX = 'auto';
      });
    }
  };

})(Drupal);
