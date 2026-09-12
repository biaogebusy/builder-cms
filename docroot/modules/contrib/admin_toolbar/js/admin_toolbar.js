/**
 * @file
 * Behaviors for the admin toolbar module.
 *
 * @param {Function} once
 *   The once library used to ensure behaviors are attached only once.
 * @param {Drupal} Drupal
 *   The Drupal global used to define behaviors.
 * @param {jQuery} $
 *   The jQuery library.
 */
((once, Drupal, $) => {
  /**
   * Initialize custom logic for the Admin Toolbar.
   *
   * @namespace Drupal.behaviors.adminToolbar
   *
   * @type {Object}
   *   More specifically, a Drupal behavior.
   *
   * @prop {Function} attach
   *   Attaches the behavior to add any custom logic to the initialization of
   *   the Admin Toolbar:
   *  - Remove the title attribute from menu links to avoid tooltip conflicts.
   *  - Add a very basic keyboard navigation to the toolbar menu.
   */
  Drupal.behaviors.adminToolbar = {
    attach: (context) => {
      // Attach only when the whole document is loaded.
      if (context !== document) {
        return;
      }
      // Ensure this behavior is only called once, when the toolbar is loaded.
      once('admin-toolbar-default', 'body', context).forEach((element) => {
        // Avoid tooltip visual conflicts with toolbar menu items (#2630724).
        element
          .querySelectorAll('#toolbar-bar a.toolbar-icon')
          .forEach((item) => {
            item.removeAttribute('title');
          });

        // Make the toolbar menu navigable with keyboard.
        $('ul.toolbar-menu li.menu-item--expanded a', element).on(
          'focusin',
          function focusIn() {
            $('li.menu-item--expanded', element).removeClass('hover-intent');
            $(this).parents('li.menu-item--expanded').addClass('hover-intent');
          },
        );

        $('ul.toolbar-menu li.menu-item a', element).keydown(
          function keyDown(e) {
            if (e.shiftKey && (e.keyCode || e.which) === 9) {
              if (
                $(this)
                  .parent('.menu-item')
                  .prev()
                  .hasClass('menu-item--expanded')
              ) {
                $(this).parent('.menu-item').prev().addClass('hover-intent');
              }
            }
          },
        );

        $('.toolbar-tab > a', element).on('focusin', () => {
          $('li.menu-item--expanded', element).removeClass('hover-intent');
        });
      });
    },
  };
})(once, Drupal, jQuery);
