/**
 * @file
 * Admin Toolbar behavior for toggling its display with a keyboard shortcut.
 */

((Drupal, once) => {
  /**
   * Implements the Admin Toolbar keyboard shortcut for toggling the toolbar.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior to toggle the display of the toolbar with a
   *   keyboard shortcut combination of keys, currently defaults to: 'Alt + p'.
   */
  Drupal.behaviors.adminToolbarToggleKeyboardShortcut = {
    attach(context) {
      if (context !== document) {
        return;
      }
      once('admin-toolbar-toggle-shortcut', 'body', context).forEach(() => {
        /**
         * Initialize the toolbar and add the toggle links.
         */

        // Check if the toolbar is hidden in local storage and hide it.
        if (
          localStorage.getItem('Drupal.adminToolbar.toggleToolbarHidden') ===
          'true'
        ) {
          // Force a transition to the hidden state.
          localStorage.setItem(
            'Drupal.adminToolbar.toggleToolbarHidden',
            false,
          );
          this.toggle();
        }

        // Shortcut 'Alt + p' will toggle the display of the toolbar.
        document.addEventListener('keydown', (event) => {
          if (event.altKey && (event.key === 'p' || event.keyCode === 80)) {
            // Prevent transmitting keypress.
            event.preventDefault();
            // Toggle the display of the toolbar.
            this.toggle();
          }
        });

        /* Create the toggle links and insert them in the DOM. */

        // Get the toolbar 'div' element to insert the toggle link after it.
        const toolbarElement = document.getElementById(
          'toolbar-administration',
        );
        if (toolbarElement) {
          // Create a toggle link to show the toolbar from the HTML string
          // template and insert it after the toolbar element.
          toolbarElement.insertAdjacentHTML(
            'afterend',
            Drupal.theme.adminToolbarToggleExpand(),
          );
          // Add a click event listener to the show toolbar link.
          toolbarElement.nextElementSibling.addEventListener(
            'click',
            this.toggle,
          );

          // Create a toggle link to hide the toolbar from the HTML string
          // template and insert the toggle link as the last toolbar tab item.
          const toolbarBar = toolbarElement.querySelector('#toolbar-bar');
          if (toolbarBar) {
            toolbarBar.insertAdjacentHTML(
              // The toolbar tab item is inserted as the last item in the toolbar.
              'beforeend',
              Drupal.theme.adminToolbarToggleCollapse(),
            );
          }

          // Add a click event listener to the collapse button.
          const toolbarIconCollapse = toolbarElement.querySelector(
            '.toolbar-tab--collapse-trigger .toolbar-icon-collapse',
          );
          if (toolbarIconCollapse) {
            toolbarIconCollapse.addEventListener('click', this.toggle);
          }
        }
      });
    },
    // Toggle the display of the toolbar from visible to hidden and vice versa.
    toggle: () => {
      // Get the body tag class list.
      const elementClassList = document.body.classList;
      // Get the current state of the toolbar from local storage.
      const toolbarHidden =
        localStorage.getItem('Drupal.adminToolbar.toggleToolbarHidden') ===
        'true';

      // If the toolbar is hidden, show it.
      if (toolbarHidden) {
        // The JS 'toggle' function can't be used here because there could be
        // conflicts with classes added by the sticky behavior. So hard 'remove'
        // function calls have to be used instead.
        elementClassList.remove('sticky-toolbar-hidden');
        elementClassList.remove('toggle-toolbar-hidden');
      } else {
        // If the toolbar is displayed, hide it.
        elementClassList.add('sticky-toolbar-hidden');
        elementClassList.add('toggle-toolbar-hidden');
      }
      // Set the new state of the toolbar in local storage.
      localStorage.setItem(
        'Drupal.adminToolbar.toggleToolbarHidden',
        !toolbarHidden,
      );
    },
  };

  /**
   * Theme function for the toolbar collapse button.
   *
   * @return {string}
   *   The HTML for the toolbar collapse button.
   */
  Drupal.theme.adminToolbarToggleCollapse = () => {
    return (
      `<div class="toolbar-tab toolbar-tab--collapse-trigger">` +
      `<button class="toolbar-icon toolbar-icon-collapse toolbar-item toolbar-item--expand toolbar-button-collapse" type="button" title="${Drupal.t('Hide Toolbar (Alt+p)')}">${Drupal.t('Hide')}</button>` +
      `</div>`
    );
  };

  /**
   * Theme function for the toolbar expand button.
   *
   * @return {string}
   *   The HTML for the toolbar expand button.
   */
  Drupal.theme.adminToolbarToggleExpand = () => {
    return (
      `<button class="toolbar-expand-floating-button" title="${Drupal.t('Show Toolbar (Alt+p)')}">` +
      `<div class="toolbar-expand-floating-button__icon"></div>` +
      `</button>`
    );
  };
})(Drupal, once);
