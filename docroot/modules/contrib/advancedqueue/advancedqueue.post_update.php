<?php

/**
 * @file
 * Post update functions for Advanced Queue.
 */

use Drupal\Core\Config\ExtensionInstallStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;

/**
 * Replace the "advancedqueue_jobs" view with the updated version.
 */
function advancedqueue_post_update_1() {
  /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $view_storage */
  $view_storage = \Drupal::entityTypeManager()->getStorage('view');
  /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $view */
  $view = $view_storage->load('advancedqueue_jobs');
  if (!$view) {
    return;
  }
  $config_storage = \Drupal::service('config.storage');
  $extension_config_storage = new ExtensionInstallStorage($config_storage, InstallStorage::CONFIG_INSTALL_DIRECTORY, StorageInterface::DEFAULT_COLLECTION, TRUE, \Drupal::installProfile());
  $config_data = $extension_config_storage->read('views.view.advancedqueue_jobs');

  $view->setSyncing(TRUE);
  // The UUID must remain unchanged between updates.
  $uuid = $view->uuid();
  $view = $view_storage->updateFromStorageRecord($view, $config_data);
  $view->set('uuid', $uuid);
  $view->save();
}
