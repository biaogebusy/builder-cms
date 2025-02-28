<?php

/**
 * @file
 * Contains the code for the profile_switcher drush commands.
 */

/**
 * Implements hook_drush_command().
 */
function profile_switcher_drush_command() {
  $items = [];
  $items['switch-profile'] = [
    'description' => 'Switch Drupal profile in a installed site',
    'arguments' => [
      'profile_name' => 'The profile to activate',
    ],
    'drupal dependencies' => ['profile_switcher'],
    'aliases' => ['switch:profile'],
  ];
  return $items;
}

/**
 * Call back function drush_custom_drush_command_().
 *
 * @param string $profile_to_install
 *   The profile to install.
 */
function drush_profile_switcher_switch_profile($profile_to_install = '') {
  $profile_to_remove = \Drupal::installProfile();

  drush_print(dt("The site's install profile will be switched from !profile_to_remove to !profile_to_install.", [
    '!profile_to_remove' => $profile_to_remove,
    '!profile_to_install' => $profile_to_install,
  ]));
  if (!drush_confirm(dt('Do you want to continue?'))) {
    return drush_user_abort();
  }

  \Drupal::service('profile_switcher.profile_switcher')->switchProfile($profile_to_install);

  drush_print('Profile changed to: ' . \Drupal::installProfile());
}
