/**
 * @file
 * Provides the processing logic for details element.
 */

(function ($) {

  'use strict';

  Drupal.FieldGroup = Drupal.FieldGroup || {};
  Drupal.FieldGroup.Effects = Drupal.FieldGroup.Effects || {};

  /**
   * This script adds the required and error classes to the details wrapper.
   */
  Drupal.behaviors.fieldGroupDetails = {
    attach: function (context) {
      $(once('field-group-details', '.field-group-details', context)).each(function () {
        var $this = $(this);

        if ($this.is('.required-fields') && ($this.find('[required]').length > 0 || $this.find('.form-required').length > 0)) {
          $('summary', $this).first().addClass('form-required');
        }
      });
    }
  };

})(jQuery);
