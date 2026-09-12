/**
 * @file
 * JavaScript file for the FooTable module.
 */

(($, Drupal, once) => {
  Drupal.behaviors.footable = {
    attach(context) {
      $(once('footable', '.footable', context)).footable();
    },
  };
})(jQuery, Drupal, once);
