/**
 * @file
 * JavaScript behaviors for webform cards.
 */

(function ($, Drupal, once) {
  /**
   * Initialize webform cards test.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.webformCardsTest = {
    attach(context) {
      $(once('webform-card-test-submit-form', '.js-webform-card-test-submit-form', context)).on('click', function () {
        var selector = $(this).attr('href').replace('#', '.') + ' .webform-button--submit';
        $(selector).trigger('click');
        return false;
      });
    }
  };

})(jQuery, Drupal, once);
