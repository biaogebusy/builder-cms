/**
 * @file
 * Defines Javascript behaviors for the flag module.
 */

(function ($, Drupal) {
  Drupal.behaviors.flagsSummary = {
    attach: (context) => {
      const $context = $(context);
      $context.find('details[data-drupal-selector="edit-flag"]').drupalSetSummary((context) => {
        const checkedBoxes = $(context).find('input:checkbox:checked');
        if (checkedBoxes.length === 0) {
          return Drupal.t('No flags');
        }
        const getTitle = () => this.title;
        return checkedBoxes.map(getTitle).toArray().join(', ');
      });
    },
  };
}(jQuery, Drupal));
