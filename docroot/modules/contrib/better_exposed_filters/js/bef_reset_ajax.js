/**
 * @file
 * bef_reset_ajax.js
 *
 * Handles reset button behavior when AJAX is enabled for BEF.
 */

(function (Drupal, once) {

  /**
   * Handles reset button clicks to properly clear URL parameters with AJAX.
   */
  Drupal.behaviors.befResetAjax = {
    attach: function (context) {
      // Match any reset button in views exposed forms.
      once('bef-reset-ajax', 'form.views-exposed-form input[name="reset"], form.views-exposed-form button[name="reset"]', context).forEach(function (resetButton) {
        resetButton.addEventListener('click', function (e) {
          e.stopImmediatePropagation();
          // Navigate to the clean URL.
          window.location.href = window.location.origin + window.location.pathname;
        });
      });
    }
  };

})(Drupal, once);
