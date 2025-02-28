/**
 * @file
 * Behaviors Bootstrap styles input scripts.
 */

(function ($, Drupal, once) {
  "use strict";

  // Adds value from input to the label to emulate a "preview" on our inputs.
  Drupal.behaviors.bootstrapStylesInputCircles = {
    attach: function (context) {
      once('bs_input-circles',".bs_input-circles input[type=radio]",context).forEach(function (value,i) {
        // Add the [key] class from the backend to our forms input element (preview our colour).
        $(value).next('label').addClass($(value).val());
      });
    }
  };

})(jQuery, Drupal, once);
