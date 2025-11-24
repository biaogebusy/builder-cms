/**
 * @file
 * Admin Toolbar Search behavior for adding a keyboard shortcut.
 */

((Drupal, once) => {
  /**
   * Implements the Admin Toolbar Search configured keyboard shortcut.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior to focus the search input field with a keyboard
   *   shortcut combination of keys, currently defaults to: 'Alt + a'.
   */
  Drupal.behaviors.adminToolbarSearchKeyboardShortcut = {
    attach: (context, settings) => {
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
            const searchInputField = context.getElementById(
              'admin-toolbar-search-input',
            );
            // If the search input field is displayed as a toolbar menu tray,
            // toggle the toolbar item so the field could be focused.
            if (settings.adminToolbarSearch.displayMenuItem) {
              searchInputField
                .closest('.toolbar-tab')
                .querySelector('.toolbar-item')
                .click();
            }
            searchInputField.focus();
            // Don't transmit the keystroke.
            event.preventDefault();
          }
        });
      });
    },
  };
})(Drupal, once);
