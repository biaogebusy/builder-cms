/**
 * @file
 * Fixes the machine name behavior when duplicating a webform.
 */

(function ($, Drupal, once) {

  'use strict';

  /**
   * Store a reference to the original machine name behavior for overriding.
   *
   * @type {Drupal~behaviorAttach}
   */
  const attach = Drupal.behaviors.machineName.attach;

  /**
   * Enhances the machine name behavior to ensure a webform's machine name is updated.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches machine-name behaviors.
   */
  Drupal.behaviors.machineName.attach = function (context, settings) {
    const $context = $(context);

    // Transfer the default value to a data attribute and clear the field value
    // to ensure the machine name is updated.
    Object.keys(settings.machineName).forEach((sourceId) => {
      const options = settings.machineName[sourceId];
      const $target = $context
        .find(options.target)
        .addClass('machine-name-target');

      // Skip if the target is not available or is disabled.
      if (!$target.length || $target[0].disabled) {
        return;
      }

      const $source = $context.find(sourceId);
      if ($source.length && $source.val()) {
        // Store the current value as a data attribute and clear the input.
        $source.data('webform_default_value', $source.val());
        $source.val('');
      }
    });

    // Invoke the original machine name behavior.
    attach.call(this, context, settings);

    // Restore the default value and trigger a change event to update the machine name.
    Object.keys(settings.machineName).forEach((sourceId) => {
      const $source = $context.find(sourceId);
      if ($source.length && $source.data('webform_default_value')) {
        $source
          .val($source.data('webform_default_value'))
          .removeData('webform_default_value')
          .change();
      }
    });

  };

})(jQuery, Drupal, once);
