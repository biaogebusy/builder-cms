/**
 * @file
 * JavaScript to autofocus first input.
 */

(function ($, Drupal) {
  /**
   * Autofocus first input.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for the webform autofocusing.
   */
  Drupal.behaviors.webformAutofocus = {
    attach(context) {
      $(context).find('.js-webform-autofocus :input:visible:enabled:first')
        .trigger('focus');
    }
  };

})(jQuery, Drupal);
