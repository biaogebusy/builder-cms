<?php

/**
 * @file
 * Webform module drush commands.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\webform_devel\Hook\WebformDevelDrushHooks;
use Drush\Drush;

/**
 * Implements hook_drush_command().
 */
#[LegacyHook]
function webform_devel_drush_command() {
  return \Drupal::service(WebformDevelDrushHooks::class)->drushCommand();
}

/**
 * Implements hook_drush_help().
 */
#[LegacyHook]
function webform_devel_drush_help($section) {
  return \Drupal::service(WebformDevelDrushHooks::class)->drushHelp($section);
}

/* ************************************************************************** */
// Reset.
/* ************************************************************************** */

/**
 * Implements drush_hook_COMMAND().
 *
 * @see \Drupal\webform_devel\Commands\WebformDevelCommands::drush_webform_devel_reset()
 */
function drush_webform_devel_reset() {
  // @phpstan-ignore-next-line
  if (!drush_confirm(dt("Are you sure you want reset the Webform module's user data and saved state?"))) {
    // @phpstan-ignore-next-line
    return drush_user_abort();
  }

  Drush::output()->writeln(dt('Resetting message closed via State API…'));
  \Drupal::state()->delete('webform.element.message');

  Drush::output()->writeln(dt('Resetting message closed via User Data…'));
  \Drupal::service('user.data')->delete('webform', NULL, 'webform.element.message');
}
