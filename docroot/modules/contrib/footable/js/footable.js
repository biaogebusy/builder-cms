/**
 * @file
 * Javascript file for the FooTable module.
 */

(function ($) {
  'use strict';

  Drupal.behaviors.footable = {
    attach: function (context) {
      $(once('footable', '.footable', context)).footable();
    }
  };
}(jQuery));
