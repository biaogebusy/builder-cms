(function (Drupal) {
  'use strict';

  /**
   * AJAX command to fill the field.
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
  Drupal.AjaxCommands.prototype.fieldWidgetActionsFillEditor = function (ajax, response, status) {
    const target = document.querySelector(response.selector);
    const data = response.data;

    if (!target) {
      console.warn('Field Widget Actions: Target element not found for selector ' + response.selector);
      return;
    }

    // Support both CK Editor 4 and 5.
    let editorInstance = null;
    if (Drupal.CKEditor5Instances && Drupal.CKEditor5Instances.has(target.id)) {

      // Get CKEditor 5+ instances.
      editorInstance = Drupal.CKEditor5Instances.get(target.id);
    }
    else if (target.classList.contains('form-textarea')) {

      // Fallback to CKEditor 4 or specific DOM attach.
      const domEditableElement = target.parentElement.querySelector('.ck-editor__editable');
      if (domEditableElement && domEditableElement.ckeditorInstance) {
        editorInstance = domEditableElement.ckeditorInstance;
      }
    }

    if (editorInstance) {

      // Setting data automatically triggers change events.
      editorInstance.setData(data);
    } else {

      // Trigger change event so other JS (autosave, states) can react.
      target.value = data;
      target.dispatchEvent(new Event('change', { bubbles: true }));
      target.dispatchEvent(new Event('input', { bubbles: true }));
    }
  };

})(Drupal);
