/**
 * @file
 * Admin Toolbar Search behavior for adding a keyboard shortcut.
 */

((Drupal, once) => {
  /**
   * Implements the Admin Toolbar Search configured keyboard shortcut.
   *
   * Depending on whether the search input field is displayed as a toolbar
   * menu tray or as a standalone input field, the behavior will focus on the
   * appropriate element when the keyboard shortcut is triggered.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior to focus the search input field with a keyboard
   *   shortcut combination of keys, currently defaults to: 'Alt + a'.
   */
  Drupal.behaviors.adminToolbarSearchKeyboardShortcut = {
    attach: (context) => {
      if (context !== document) {
        return;
      }
      once(
        'admin-toolbar-search-keyboard-shortcut',
        '#toolbar-bar',
        context,
      ).forEach(() => {
        // Shortcut 'Alt + a' will focus on the search form.
        document.addEventListener('keydown', (event) => {
          if (event.altKey && (event.key === 'a' || event.keyCode === 65)) {
            // Get the search tab which should *always* be loaded.
            const searchTab = context.getElementById(
              'admin-toolbar-search-tab',
            );
            // Get the computed style of the search tab to check if it is
            // visible based on the toolbar width breakpoint.
            const searchTabStyle = window.getComputedStyle(searchTab);
            // Get the search field tab which could be empty ('null') if the
            // setting 'display_menu_item' is enabled.
            const searchFieldTab = context.getElementById(
              'admin-toolbar-search-field-tab',
            );

            // If the search field tab is loaded and the search tab is not
            // visible, focus on the search input field directly.
            if (searchFieldTab !== null && searchTabStyle.display === 'none') {
              searchFieldTab
                .querySelector('#admin-toolbar-search-field-input')
                .focus();
            } else {
              // If the search input field is displayed as a toolbar menu tray
              // and is not visible, toggle its display to focus its field.
              const searchTabToolbarItem =
                searchTab.querySelector('.toolbar-item');
              if (!searchTabToolbarItem.classList.contains('is-active')) {
                searchTabToolbarItem.click();
              }
              searchTab.querySelector('#admin-toolbar-search-input').focus();
            }
            // Don't transmit the keystroke.
            event.preventDefault();
          }
        });
      });
    },
  };
})(Drupal, once);
