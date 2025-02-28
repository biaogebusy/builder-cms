(function (Drupal) {
  'use strict';

  Drupal.behaviors.PhoneInternational = {
    attach: function (context, settings) {
      // Do something like jquery.once. Be sure that this attach only runs once.
      var fields = document.querySelectorAll('.phone_intl');
      if (fields.length) {
        if (!document.querySelector(".jsIntPhone")) {
          document.querySelector('.phone_intl').classList.add('jsIntPhone');

          // Loop each one and load the library.
          fields.forEach(function(field) {
            // As we are using attach form, check first if its already loaded.
            var parent = field.parentElement;
            if (!parent.classList.contains('intl-tel-input')) {
              // Find the field that writes the code.
              var field_code = document.querySelector('[name="' + field.name + '"]');
              // Initialize the phone library.
              var iti = window.intlTelInput(field, {
                initialCountry: 'us',
              });
              // Listen to the telephone input for changes. And get the dialcode.
              field.addEventListener('countrychange', function(e) {
                if (iti.getSelectedCountryData().dialCode != undefined && !field.value.length) {
                  field_code.value = '+' + iti.getSelectedCountryData().dialCode;
                }
              });
            }
          });

        }
      }
    }
  };

})(Drupal);
