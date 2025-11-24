/**
 * @file
 * Admin Toolbar default hover behavior for the display of the menu.
 */

((once) => {
  /**
   * Implements the Admin Toolbar default hover behavior.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for the display of the menu on hover.
   */
  Drupal.behaviors.adminToolbarHover = {
    attach: (context) => {
      if (context !== document) {
        return;
      }
      // Attach Vanilla JS hover behavior to the menu items.
      once('admin-toolbar-hover', 'body', context).forEach((element) => {
        // Attach a basic mouseenter/mouseleave behavior which adds or removes
        // a CSS class to the menu items containing sub-menus. All the menu
        // items selected with 'li.menu-item--expanded', inside the toolbar.
        element
          .querySelectorAll(
            '.toolbar-tray.toolbar-tray-horizontal .menu-item.menu-item--expanded',
          )
          .forEach((item) => {
            /* eslint max-nested-callbacks: ["error", 5] */
            item.addEventListener('mouseenter', () => {
              // Avoid using parentElement to prevent the JS from breaking with
              // HTML structure changes: Get the closest toolbar menu parent
              // list item and remove the class 'hover-intent' from all expanded
              // siblings menu items.
              item
                .closest('.toolbar-menu')
                .querySelectorAll('.menu-item.menu-item--expanded')
                .forEach((listItem) => {
                  listItem.classList.remove('hover-intent');
                });
              // Add the class 'hover-intent' to the current menu item.
              item.classList.add('hover-intent');
            });
            item.addEventListener('mouseleave', () => {
              // Remove the class 'hover-intent' from the current menu item.
              item.classList.remove('hover-intent');
            });
          });
      });
    },
  };
})(once);
