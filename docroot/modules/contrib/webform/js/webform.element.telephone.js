/**
 * @file
 * JavaScript behaviors for Telephone element.
 */

(function ($, Drupal, drupalSettings, once) {
  // @see https://github.com/jackocnr/intl-tel-input#options
  Drupal.webform = Drupal.webform || {};
  Drupal.webform.intlTelInput = Drupal.webform.intlTelInput || {};
  Drupal.webform.intlTelInput.options = Drupal.webform.intlTelInput.options || {};

  /**
   * Initialize Telephone international element.
   * @see http://intl-tel-input.com/node_modules/intl-tel-input/examples/gen/is-valid-number.html
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.webformTelephoneInternational = {
    attach: function (context) {
      if (!$.fn.intlTelInput) {
        return;
      }

      $(once('webform-telephone-international', 'input.js-webform-telephone-international', context)).each(function () {
        var $telephone = $(this);

        // Add error message container.
        var $error = $('<strong class="error form-item--error-message">' + Drupal.t('Invalid phone number') + '</strong>').hide();
        $telephone.closest('.js-form-item').append($error);

        var options = {
          // The utilsScript is fetched when the page has finished.
          // @see \Drupal\webform\Plugin\WebformElement\Telephone::prepare
          // @see https://github.com/jackocnr/intl-tel-input
          utilsScript: drupalSettings.webform.intlTelInput.utilsScript,
          nationalMode: false
        };

        // Parse data attributes.
        if ($telephone.attr('data-webform-telephone-international-initial-country')) {
          options.initialCountry = $telephone.attr('data-webform-telephone-international-initial-country');
        }
        if ($telephone.attr('data-webform-telephone-international-preferred-countries')) {
          options.preferredCountries = JSON.parse($telephone.attr('data-webform-telephone-international-preferred-countries'));
        }

        options = $.extend(options, Drupal.webform.intlTelInput.options);

        // Allow custom options.
        if ($telephone.attr('data-options')) {
          options = $.extend(true, options, JSON.parse($telephone.attr('data-options')));
        }

        $telephone.intlTelInput(options);

        var reset = function () {
          $telephone.removeClass('error');
          $error.hide();
        };

        var validate = function () {
          return Drupal.webformTelephoneInternationalValidate($telephone, $error);
        };

        $telephone.on('blur', function () {
          reset();
          validate();
        });

        $telephone.on('keyup change', reset);

        // Check for a valid phone number on submit.
        var $form = $(this.form);
        $form.on('submit', function (event) {
          if (!validate()) {
            $telephone.focus();
            event.preventDefault();

            // On validation error make sure to clear submit the once behavior.
            // @see Drupal.behaviors.webformSubmitOnce
            // @see webform.form.submit_once.js
            if (Drupal.behaviors.webformSubmitOnce) {
              Drupal.behaviors.webformSubmitOnce.clear();
            }
          }
        });
      });
    }
  };

  /**
   * Validates a given telephone number within a webform.
   *
   * @param {jQuery} $telephone
   *   A telephone element.
   * @param {jQuery} $error
   *   A error element.
   *
   * @returns {boolean}
   *   Returns true if the telephone number is valid; otherwise, false.
   */
  Drupal.webformTelephoneInternationalValidate = function ($telephone, $error) {
    if (!$telephone.val().trim() || $telephone.intlTelInput('isValidNumber') !== false) {
      return true;
    }

    $telephone.addClass('error');
    var placeholder = $telephone.attr('placeholder');
    var message;
    if (placeholder) {
      message = Drupal.t('The phone number is not valid. (e.g. @example)', {'@example': placeholder});
    }
    else {
      message = Drupal.t('The phone number is not valid.');
    }
    $error.html(message).show();
    return false;
  };

})(jQuery, Drupal, drupalSettings, once);
