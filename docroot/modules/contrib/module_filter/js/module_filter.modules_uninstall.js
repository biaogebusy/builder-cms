/**
 * @file
 * Module filter behaviors for the uninstall page.
 */

(($, Drupal) => {
  /**
   * Filter enhancements.
   */
  Drupal.behaviors.moduleFilterModulesUninstall = {
    attach(context) {
      const $input = $(
        once('module-filter', 'input.table-filter-text', context),
      );
      if ($input.length) {
        const wrapperId = $input.attr('data-table');
        const $wrapper = $(wrapperId);
        const selector = 'tbody tr';

        $wrapper
          .children('details')
          .wrapAll('<div class="modules-uninstall-wrapper"></div>');
        const $modulesWrapper = $('.modules-uninstall-wrapper', $wrapper);

        $input
          .winnow(`${wrapperId} ${selector}`, {
            // The table-filter-text-source class will pick up the module name and
            // machine name. The description does not have this class so we need
            // to explicitly include the module-description class.
            textSelector: '.table-filter-text-source, td .module-description',
            emptyMessage: Drupal.t('No results'),
            wrapper: $modulesWrapper,
            additionalOperators: {
              description(string, item) {
                if (item.description === undefined) {
                  // Soft cache.
                  item.description = $('.module-description', item.element)
                    .text()
                    .toLowerCase();
                }
                if (item.description.indexOf(string) >= 0) {
                  return true;
                }
              },
            },
          })
          .focus();

        $input.bind('winnow:finish', () => {
          Drupal.announce(
            Drupal.formatPlural(
              $modulesWrapper.find(`${selector}:visible`).length,
              '1 module is available in the modified list.',
              '@count modules are available in the modified list.',
            ),
          );
        });
      }
    },
  };
})(jQuery, Drupal, once);
