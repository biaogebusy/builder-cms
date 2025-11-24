/**
 * @file
 * JavaScript functionality for the private message notification block.
 */

((Drupal, drupalSettings, window) => {
  let checkingCountInProgress = false;
  let updateTimeoutId = null;

  /**
   * Private message notification block.
   */
  Drupal.privateMessageNotificationBlock = {
    /**
     * Triggers AJAX to get the new unread thread count from the server.
     */
    triggerCountCallback() {
      if (checkingCountInProgress) {
        return;
      }

      checkingCountInProgress = true;
      Drupal.ajax({
        url: drupalSettings.privateMessageNotificationBlock
          .newMessageCountCallback,
        error: (err) => {
          window.location.reload();
          console.error(err);
        },
      }).execute();
    },

    /**
     * Updates the page.
     *
     * @param {number} unreadItemsCount
     *   Unread items.
     */
    triggerPageUpdate(unreadItemsCount) {
      this.updateNotificationBlock(unreadItemsCount);
      this.updatePageTitle(unreadItemsCount);
    },

    /**
     * Updates notification block.
     *
     * @param {number} unreadItemsCount
     *   Unread items.
     */
    updateNotificationBlock(unreadItemsCount) {
      const notificationWrapper = document.querySelector(
        '.private-message-notification-wrapper',
      );

      if (!notificationWrapper) {
        return;
      }

      if (unreadItemsCount) {
        notificationWrapper.classList.add('unread-threads');
      } else {
        notificationWrapper.classList.remove('unread-threads');
      }

      const notificationLink = notificationWrapper.querySelector(
        '.private-message-page-link',
      );
      if (notificationLink) {
        notificationLink.textContent = unreadItemsCount;
      }
    },

    /**
     * Updates page title.
     *
     * @param {number} unreadItemsCount
     *   Unread items.
     */
    updatePageTitle(unreadItemsCount) {
      const pageTitle = document.querySelector('head title');
      const titlePattern = /^\(\d+\)\s/;

      if (!pageTitle) {
        return;
      }

      if (unreadItemsCount > 0) {
        pageTitle.textContent = pageTitle.textContent.replace(titlePattern, '');
        pageTitle.textContent = `(${unreadItemsCount}) ${pageTitle.textContent}`;
      } else {
        pageTitle.textContent = pageTitle.textContent.replace(titlePattern, '');
      }
    },

    /**
     * Sets a timeout for count updates.
     */
    scheduleCountUpdate() {
      if (updateTimeoutId) {
        window.clearTimeout(updateTimeoutId);
      }

      const refreshRate =
        drupalSettings.privateMessageNotificationBlock.ajaxRefreshRate * 1000;
      if (refreshRate) {
        updateTimeoutId = window.setTimeout(
          Drupal.privateMessageNotificationBlock.triggerCountCallback,
          refreshRate,
        );
      }
    },
  };

  /**
   * Attaches the batch behavior to notification block.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.privateMessageNotificationBlock = {
    attach(context) {
      const notificationWrapper = once(
        'private-message-notification-block',
        '.private-message-notification-wrapper',
        context,
      ).shift();

      if (notificationWrapper) {
        Drupal.privateMessageNotificationBlock.scheduleCountUpdate();
      }
    },
  };

  /**
   * Ajax command to update the unread items count.
   *
   * @param {Drupal.Ajax} ajax
   *   {@link Drupal.Ajax} object created by {@link Drupal.ajax}.
   * @param {object} response
   *   JSON response from the Ajax request.
   */
  Drupal.AjaxCommands.prototype.privateMessageUpdateUnreadItemsCount = (
    ajax,
    response,
  ) => {
    Drupal.privateMessageNotificationBlock.triggerPageUpdate(
      response.unreadItemsCount,
    );

    checkingCountInProgress = false;
    Drupal.privateMessageNotificationBlock.scheduleCountUpdate();
  };
})(Drupal, drupalSettings, window);
