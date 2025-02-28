/**
 * @file
 * Adds JavaScript functionality to the uninstall preparation confirm page.
 */

(function ($, Drupal, window, once) {

  'use strict';

  function uninstallButtonWatcher(context) {
    $(once('uninstall-button-watcher', '#private-message-admin-uninstall-form #edit-submit', context)).each(function () {
      $(this).click(function () {
        return window.confirm(Drupal.t('This will delete all private message content from the database. Are you absolutely sure you wish to proceed?'));
      });
    });
  }

  Drupal.behaviors.privateMessageUninstallPrepConfirm = {
    attach: function (context) {
      uninstallButtonWatcher(context);
    }
  };

}(jQuery, Drupal, window, once));
