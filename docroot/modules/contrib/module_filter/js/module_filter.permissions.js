/**
 * @file
 * Module filter behaviors for the permissions page.
 */

(($) => {
  /**
   * Filter enhancements.
   */
  Drupal.behaviors.moduleFilterPermissions = {
    attach(context) {
      const $input = $(
        once('module-filter', 'input.table-filter-text', context),
      );
      if ($input.length) {
        const wrapperId = $input.attr('data-table');
        const selector = 'tbody tr';
        let lastModuleItem;

        // Move location of filter input to before the permissions table.
        $(wrapperId).parent().prepend($input.closest('.table-filter'));

        $input.winnow(`${wrapperId} ${selector}`, {
          // Match on module name or permission text.
          textSelector: 'td.module, div.permission',
          buildIndex: [
            (item) => {
              // Use .module class to determine if it is a module or a permission.
              item.isModule = item.element.has('.module').length;

              if (item.isModule) {
                // This is a module so initialise the children array to store
                // the permission items.
                item.children = [];
                lastModuleItem = item;
              } else {
                // This is a permission item, so record its parent and add it to
                // its parents array of children items.
                item.parent = lastModuleItem;
                lastModuleItem.children.push(item);
              }

              return item;
            },
          ],
          additionalOperators: {
            perm(string, item) {
              if (!item.isModule) {
                if (item.permission === undefined) {
                  item.permission = $('.permission .title', item.element)
                    .text()
                    .toLowerCase();
                }

                if (item.permission.indexOf(string) >= 0) {
                  return true;
                }
              }
            },
          },
        });

        const winnow = $input.data('winnow');
        $input.bind('winnow:finish', () => {
          if (winnow.results.length > 0) {
            for (const i in winnow.results) {
              if (winnow.results[i].isModule) {
                // The match is a module name so also show all the permissions.
                for (const k in winnow.results[i].children) {
                  winnow.results[i].children[k].element.show();
                }
              }
              // Otherwise it is a permission, so show the module name.
              else {
                winnow.results[i].parent.element.show();
              }
            }
          }
        });
      }
    },
  };
})(jQuery, once);
