/**
 * @file
 * Module filter behaviors.
 */

(function ($, Drupal) {
  Drupal.ModuleFilter = Drupal.ModuleFilter || {};
  const { ModuleFilter } = Drupal;

  /**
   * Filter enhancements.
   */
  Drupal.behaviors.moduleFilterModules = {
    attach(context) {
      const $input = $(
        once('module-filter', 'input.table-filter-text', context),
      );
      if ($input.length) {
        ModuleFilter.input = $input;
        ModuleFilter.selector = 'tbody tr';
        ModuleFilter.wrapperId = ModuleFilter.input.attr('data-table');
        ModuleFilter.wrapper = $(ModuleFilter.wrapperId);
        const $enabled = $(
          '.table-filter [name="checkboxes[enabled]"]',
          ModuleFilter.wrapper,
        );
        const $disabled = $(
          '.table-filter [name="checkboxes[disabled]"]',
          ModuleFilter.wrapper,
        );
        const $unavailable = $(
          '.table-filter [name="checkboxes[unavailable]"]',
          ModuleFilter.wrapper,
        );

        let showEnabled =
          ModuleFilter.localStorage.getBoolean('modules.enabled');
        if (showEnabled == null) {
          showEnabled = $enabled.is(':checked');
        }
        $enabled.prop('checked', showEnabled);
        let showDisabled =
          ModuleFilter.localStorage.getBoolean('modules.disabled');
        if (showDisabled == null) {
          showDisabled = $disabled.is(':checked');
        }
        $disabled.prop('checked', showDisabled);
        let showUnavailable = ModuleFilter.localStorage.getBoolean(
          'modules.unavailable',
        );
        if (showUnavailable == null) {
          showUnavailable = $unavailable.is(':checked');
        }
        $unavailable.prop('checked', showUnavailable);

        ModuleFilter.wrapper
          .children('details')
          .wrapAll('<div class="modules-wrapper"></div>');
        ModuleFilter.modulesWrapper = $(
          '.modules-wrapper',
          ModuleFilter.wrapper,
        );

        ModuleFilter.input
          .winnow(`${ModuleFilter.wrapperId} ${ModuleFilter.selector}`, {
            textSelector:
              'td.module .module-name, .module-machine-name, .module-description',
            emptyMessage: Drupal.t('No results'),
            wrapper: ModuleFilter.modulesWrapper,
            buildIndex: [
              function (item) {
                const $checkbox = $('td.checkbox :checkbox', item.element);
                if ($checkbox.length > 0) {
                  item.status = $checkbox.is(':checked');
                  item.disabled = $checkbox.is(':disabled');
                } else {
                  item.status = false;
                  item.disabled = true;
                }
                return item;
              },
            ],
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
              requiredBy(string, item) {
                if (item.requiredBy === undefined) {
                  // Soft cache.
                  item.requiredBy = [];
                  $('.admin-requirements.required-by li', item.element).each(
                    function () {
                      const moduleName = $(this)
                        .text()
                        .toLowerCase()
                        .replace(/\([a-z]*\)/g, '');
                      item.requiredBy.push($.trim(moduleName));
                    },
                  );
                }

                if (item.requiredBy.length) {
                  for (const i in item.requiredBy) {
                    if (item.requiredBy[i].indexOf(string) >= 0) {
                      return true;
                    }
                  }
                }
              },
              requires(string, item) {
                if (item.requires === undefined) {
                  // Soft cache.
                  item.requires = [];
                  $('.admin-requirements.requires li', item.element).each(
                    function () {
                      const moduleName = $(this)
                        .text()
                        .toLowerCase()
                        .replace(/\([a-z]*\)/g, '');
                      item.requires.push($.trim(moduleName));
                    },
                  );
                }

                if (item.requires.length) {
                  for (const i in item.requires) {
                    if (item.requires[i].indexOf(string) >= 0) {
                      return true;
                    }
                  }
                }
              },
            },
            rules: [
              function (item) {
                if (showEnabled) {
                  if (item.status === true && item.disabled === true) {
                    return true;
                  }
                }
                if (showDisabled) {
                  if (item.status === false && item.disabled === false) {
                    return true;
                  }
                }
                if (showUnavailable) {
                  if (item.status === false && item.disabled === true) {
                    return true;
                  }
                }

                return false;
              },
            ],
          })
          .focus();
        ModuleFilter.winnow = ModuleFilter.input.data('winnow');

        ModuleFilter.input.bind('winnow:finish', function () {
          Drupal.announce(
            Drupal.formatPlural(
              ModuleFilter.modulesWrapper.find(
                `${ModuleFilter.selector}:visible`,
              ).length,
              '1 module is available in the modified list.',
              '@count modules are available in the modified list.',
            ),
          );
        });

        $enabled.change(function () {
          showEnabled = $(this).is(':checked');
          ModuleFilter.localStorage.setItem('modules.enabled', showEnabled);
          ModuleFilter.winnow.filter();
        });
        $disabled.change(function () {
          showDisabled = $disabled.is(':checked');
          ModuleFilter.localStorage.setItem('modules.disabled', showDisabled);
          ModuleFilter.winnow.filter();
        });
        $unavailable.change(function () {
          showUnavailable = $unavailable.is(':checked');
          ModuleFilter.localStorage.setItem(
            'modules.unavailable',
            showUnavailable,
          );
          ModuleFilter.winnow.filter();
        });
      }
    },
  };
})(jQuery, Drupal, once);
