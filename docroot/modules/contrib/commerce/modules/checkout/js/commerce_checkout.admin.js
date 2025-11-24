/**
 * @file
 * Defines behaviors for the checkout admin UI.
 */

(($, Drupal) => {
  /**
   * Attaches the checkoutPaneOverview behavior.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the checkoutPaneOverview behavior.
   *
   * @see Drupal.checkoutPaneOverview.attach
   */
  Drupal.behaviors.checkoutPaneOverview = {
    attach: (context) => {
      $(
        once('checkout-pane-overview', 'table#checkout-pane-overview', context),
      ).each((index, element) => {
        Drupal.checkoutPaneOverview.attach(element);
      });
    },
  };
  /**
   * Namespace for the checkout pane overview.
   *
   * @namespace
   */
  Drupal.checkoutPaneOverview = {
    /**
     * Attaches the checkoutPaneOverview behavior.
     *
     * @param {HTMLTableElement} table
     *   The table element for the overview.
     */
    attach(table) {
      const tableDrag = Drupal.tableDrag[table.id];

      // Add custom tabledrag callbacks.
      tableDrag.onDrop = this.onDrop;
      tableDrag.row.prototype.onSwap = this.onSwap;
    },
    /**
     * Updates the dropped row (Step dropdown, settings display).
     */
    onDrop() {
      const dragObject = this;
      const $rowElement = $(dragObject.rowObject.element);
      const regionRow = $rowElement.prevAll('tr.region-message').get(0);
      const regionName = regionRow.className.replace(
        /([^ ]+[ ]+)*region-([^ ]+)-message([ ]+[^ ]+)*/,
        '$2',
      );
      const regionField = $rowElement.find('select.pane-step');

      // Keep the Step dropdown up to date.
      regionField.val(regionName);
      // Hide the settings in the disabled region.
      if (regionName === '_disabled') {
        $rowElement.find('.pane-configuration-summary').hide();
        $rowElement.find('.pane-configuration-edit-wrapper').hide();
      } else {
        $rowElement.find('.pane-configuration-summary').show();
        $rowElement.find('.pane-configuration-edit-wrapper').show();
      }
    },
    /**
     * Refreshes placeholder rows in empty regions while a row is being dragged.
     */
    onSwap() {
      const rowObject = this;
      $(rowObject.table)
        .find('tr.region-message')
        .each(function () {
          const message = this;
          const $message = $(message);
          // If the dragged row is in this region, but above the message row, swap
          // it down one space.
          if (
            $message.prev('tr').get(0) ===
            rowObject.group[rowObject.group.length - 1]
          ) {
            // Prevent a recursion problem when using the keyboard to move rows
            // up.
            if (
              rowObject.method !== 'keyboard' ||
              rowObject.direction === 'down'
            ) {
              rowObject.swap('after', this);
            }
          }
          const nextRow = message.nextElementSibling;
          // This region has become empty.
          if (!nextRow || !nextRow.matches('.draggable')) {
            $message.removeClass('region-populated').addClass('region-empty');
          }
          // This region has become populated.
          else if (message.matches('.region-empty')) {
            $message.removeClass('region-empty').addClass('region-populated');
          }
        });
    },
  };
})(jQuery, Drupal);
