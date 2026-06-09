(function (Drupal) {

  'use strict';

  /**
   * AJAX command to fill a simple field widget (input, textarea) with data.
   *
   * @param {Drupal.Ajax} [ajax]
   *   The ajax object.
   * @param {object} response
   *   The response object.
   * @param {string} response.selector
   *   The target selector.
   * @param {string} response.data
   *   The value to insert.
   * @param {number} [status]
   *   The HTTP status code.
   */
  Drupal.AjaxCommands.prototype.fieldWidgetActionsFillSimpleField = function (ajax, response, status) {
    const target = document.querySelector(response.selector);
    const data = response.data;

    if (!target) {
      console.warn('Field Widget Actions: Target element not found for selector ' + response.selector);
      return;
    }

    // Set the value directly.
    target.value = data;

    // Trigger change event so other JS (autosave, states) can react.
    target.dispatchEvent(new Event('input', { bubbles: true }));
    target.dispatchEvent(new Event('change', { bubbles: true }));
  };

})(Drupal);
