/**
 * @file
 * Module filter behaviors for the update status page.
 */

(($, Drupal) => {
  Drupal.ModuleFilter = Drupal.ModuleFilter || {};

  /**
   * Filter enhancements.
   */
  Drupal.behaviors.moduleFilterUpdateStatus = {
    attach() {
      const $input = $(once('module-filter', 'input.table-filter-text'));
      if ($input.length) {
        const selector = 'tbody tr';
        const wrapperId = $input.attr('data-table');
        const $wrapper = $(wrapperId);

        const $show = $('.table-filter input[name="show"]', $wrapper);
        let show =
          Drupal.ModuleFilter.localStorage.getItem('updateStatus.show') ||
          'all';

        $input
          .winnow(`${wrapperId} ${selector}`, {
            textSelector: 'td .project-update__title a',
            emptyMessage: Drupal.t('No results'),
            wrapper: $wrapper,
            buildIndex: [
              (item) => {
                if (item.element.is('.color-success')) {
                  item.state = 'ok';
                } else if (item.element.is('.color-warning')) {
                  item.state = 'warning';
                } else if (item.element.is('.color-error')) {
                  item.state = 'error';
                  if (
                    item.element.has('.project-update__status--security-error')
                      .length
                  ) {
                    item.state = 'security-error';
                  } else if (
                    item.element.has('.project-update__status--not-supported')
                      .length
                  ) {
                    item.state = 'unsupported-error';
                  }
                  return item;
                }
              },
            ],
            rules: [
              (item) => {
                switch (show) {
                  case 'updates':
                    if (
                      item.state === 'warning' ||
                      item.state === 'error' ||
                      item.state === 'security-error' ||
                      item.state === 'unsupported-error'
                    ) {
                      return true;
                    }
                    break;

                  case 'ignore':
                    if (item.state === 'ignored') {
                      return true;
                    }
                    break;

                  case 'security':
                    if (item.state === 'security-error') {
                      return true;
                    }
                    break;

                  case 'unsupported':
                    if (item.state === 'unsupported-error') {
                      return true;
                    }
                    break;

                  case 'all':
                  default:
                    return true;
                }

                return false;
              },
            ],
          })
          .focus();
        Drupal.ModuleFilter.winnow = $input.data('winnow');

        const $titles = $('h3', $wrapper);
        $input.bind('winnow:finish', () => {
          $titles.each((index, element) => {
            const $title = $(element);
            const $table = $title.next();
            if ($table.is('table')) {
              const $visibleRows = $table.find(`${selector}:visible`);
              $title.toggle($visibleRows.length > 0);
            }
          });

          Drupal.announce(
            Drupal.formatPlural(
              $wrapper.find(`${selector}:visible`).length,
              '1 project is available in the modified list.',
              '@count projects are available in the modified list.',
            ),
          );
        });

        $show.change((event) => {
          show = $(event.currentTarget).val();
          Drupal.ModuleFilter.localStorage.setItem('updateStatus.show', show);
          Drupal.ModuleFilter.winnow.filter();
        });
        $show
          .filter(`[value="${show}"]`)
          .prop('checked', true)
          .trigger('change');
      }
    },
  };
})(jQuery, Drupal, once);
