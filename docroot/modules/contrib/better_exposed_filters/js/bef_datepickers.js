/**
 * @file
 * bef_datepickers.js
 *
 * Provides jQueryUI Datepicker integration with Better Exposed Filters.
 */

(function ($, Drupal, drupalSettings) {
  /*
   * Helper functions
   */
  Drupal.behaviors.betterExposedFiltersDatePickers = {
    attach: function (context, settings) {

      const datepickers = document.querySelectorAll('.bef-datepicker');
      datepickers.forEach(function (input) {
        const defaultValue = input.getAttribute('default_value');

        if (defaultValue) {
          input.value = defaultValue;
        }
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
