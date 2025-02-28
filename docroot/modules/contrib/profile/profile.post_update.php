<?php

/**
 * @file
 * Post update functions for Profile.
 */

use Drupal\Core\Config\ExtensionInstallStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\profile\Entity\ProfileType;
use Drupal\system\Entity\Action;

/**
 * Change the plugin ID of the delete action.
 */
function profile_post_update_change_delete_action_plugin() {
  $action_storage = \Drupal::entityTypeManager()->getStorage('action');
  $action = $action_storage->load('profile_delete_action');
  if ($action instanceof Action) {
    $action->setPlugin('entity:delete_action:profile');
    $action->save();
  }
}

/**
 * Change the plugin IDs of the publish and unpublish actions.
 */
function profile_post_update_change_publish_action_plugins() {
  $action_storage = \Drupal::entityTypeManager()->getStorage('action');

  $publish_action = $action_storage->load('profile_publish_action');
  if ($publish_action instanceof Action) {
    $publish_action->setPlugin('entity:publish_action:profile');
    $publish_action->save();
  }
  $unpublish_action = $action_storage->load('profile_unpublish_action');
  if ($unpublish_action instanceof Action) {
    $unpublish_action->setPlugin('entity:unpublish_action:profile');
    $unpublish_action->save();
  }
}

/**
 * Add new revision settings to profile types.
 */
function profile_post_update_add_revision_settings() {
  /** @var \Drupal\profile\Entity\ProfileType[] $profile_types */
  $profile_types = ProfileType::loadMultiple();
  foreach ($profile_types as $profile_type) {
    if ($profile_type->get('use_revisions')) {
      $profile_type->set('allow_revisions', TRUE);
      $profile_type->set('new_revision', TRUE);
      $profile_type->save();
    }
  }
}

/**
 * Show the profile form at user registration using the profile_form widget.
 */
function profile_post_update_configure_register_form_mode() {
  $profile_types = ProfileType::loadMultiple();
  $profile_types = array_filter($profile_types, function (ProfileType $profile_type) {
    return $profile_type->getRegistration();
  });
  if (!$profile_types) {
    // No profile types to update.
    return;
  }

  $register_display = EntityFormDisplay::load('user.user.register');
  if (!$register_display) {
    // The "register" form mode isn't customized by default.
    $default_display = EntityFormDisplay::load('user.user.default');
    $register_display = $default_display->createCopy('register');
  }
  // Assign the inline widget to each computed field.
  $weight = 90;
  foreach ($profile_types as $profile_type) {
    $register_display->setComponent($profile_type->id() . '_profiles', [
      'type' => 'profile_form',
      'weight' => ++$weight,
    ]);
  }
  $register_display->setStatus(TRUE);
  $register_display->save();
}

/**
 * Replace the "profiles" view with the updated version.
 */
function profile_post_update_replace_view() {
  /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $view_storage */
  $view_storage = \Drupal::entityTypeManager()->getStorage('view');
  /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $view */
  $view = $view_storage->load('profiles');
  if (!$view) {
    return;
  }
  $config_storage = \Drupal::service('config.storage');
  $extension_config_storage = new ExtensionInstallStorage($config_storage, InstallStorage::CONFIG_INSTALL_DIRECTORY, StorageInterface::DEFAULT_COLLECTION, TRUE, NULL);
  $config_data = $extension_config_storage->read('views.view.profiles');

  $view->setSyncing(TRUE);
  // The UUID must remain unchanged between updates.
  $uuid = $view->uuid();
  $view = $view_storage->updateFromStorageRecord($view, $config_data);
  $view->set('uuid', $uuid);
  $view->save();
}
