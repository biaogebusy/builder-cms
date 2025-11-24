/**
 * @file
 * Admin Toolbar hoverIntent plugin behavior for the display of the menu.
 */

(($, Drupal, once) => {
  /**
   * Implements the Admin Toolbar hoverIntent plugin behavior.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior for the display of the menu on hover.
   */
  Drupal.behaviors.adminToolbarHoverIntent = {
    attach: (context, settings) => {
      if (context !== document) {
        return;
      }
      once('admin-toolbar-hover-plugin', 'body', context).forEach((element) => {
        // Call the hoverIntent JQuery plugin on the menu items.
        $(
          '.toolbar-tray-horizontal li.menu-item--expanded, .toolbar-tray-horizontal ul li.menu-item--expanded .menu-item',
          element,
        ).hoverIntent({
          over() {
            // At the current depth, we should delete all 'hover-intent'
            // classes. Other wise we get unwanted behavior where menu items are
            // expanded while already in hovering other ones.
            $(this).parent().find('li').removeClass('hover-intent');
            $(this).addClass('hover-intent');
          },
          out() {
            $(this).removeClass('hover-intent');
          },
          timeout: settings.hoverIntentTimeout,
        });
      });
    },
  };
})(jQuery, Drupal, once);
