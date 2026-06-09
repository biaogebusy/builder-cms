/**
 * @file
 * Defines JavaScript behaviors for the ai content suggestions settings form.
 */

(($, Drupal) => {
  /**
   * Behaviors for summaries for tabs in the ai content suggestions settings form.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches summary behavior for tabs in the ai content suggestions settings form.
   */
  Drupal.behaviors.aiContentSuggestionsFormSummaries = {
    attach(context) {
      $('.entity-type-tab', context).drupalSetSummary((tab) => {
        const mode = $('input.form-radio:checked', tab);
        const enabled = $('input.entity-type-bundles:checked', tab);
        if (mode.val() == 'enable') {
          return enabled.length > 0 ? Drupal.t('Enabled') : '';
        }
        else {
          return Drupal.t('Enabled');
        }
      });
    },
  };
})(jQuery, Drupal);
