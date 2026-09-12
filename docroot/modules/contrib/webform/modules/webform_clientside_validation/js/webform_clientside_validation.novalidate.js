/**
 * @file
 * Attaches behaviors for the Clientside Validation jQuery module.
 */
(function ($, Drupal, once) {
  /**
   * Disable clientside validation for webforms.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.webformClientSideValidationNoValidation = {
    attach(context) {
      $(once('webformClientSideValidationNoValidate', 'form[data-webform-clientside-validation-novalidate]', context))
        .each(function () {
          $(this).validate().destroy();
        });
    }
  };

})(jQuery, Drupal, once);
