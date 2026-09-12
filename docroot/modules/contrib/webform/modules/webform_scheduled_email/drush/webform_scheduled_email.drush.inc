<?php

/**
 * @file
 * Webform scheduled email module drush commands.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\webform_scheduled_email\Hook\WebformScheduledEmailDrushHooks;
use Drupal\webform\Entity\Webform;
use Drupal\webform_scheduled_email\Plugin\WebformHandler\ScheduleEmailWebformHandler;
use Drush\Drush;

/**
 * Implements hook_drush_command().
 */
#[LegacyHook]
function webform_scheduled_email_drush_command() {
  return \Drupal::service(WebformScheduledEmailDrushHooks::class)->drushCommand();
}

/**
 * Implements hook_drush_help().
 */
function webform_scheduled_email_help($section) {
  switch ($section) {
    case 'drush:webform-scheduled-email-cron':
      return dt('This command will export webform submissions to a file.');

    case 'meta:webform_scheduled_email:title':
      return dt('Webform scheduled email commands');

    case 'meta:webform_scheduled_email:summary':
      return dt('Allows webform emails to be scheduled.');
  }
}

/* ************************************************************************** */
// Export.
/* ************************************************************************** */

/**
 * Implements drush_hook_COMMAND().
 *
 * NOTE:
 * Unable to use drush_hook_COMMAND_validate because we are using a custom
 * callback to prevent conflicts with webform_scheduled_email_cron().
 *
 * @see webform_scheduled_email_cron()
 * @see \Drupal\webform_scheduled_email\Commands\WebformScheduledEmailCommands::drush_webform_scheduled_email_cron_validate()
 * @see \Drupal\webform_scheduled_email\Commands\WebformScheduledEmailCommands::drush_webform_scheduled_email_cron()
 */
function webform_scheduled_email_cron_process($webform_id = NULL, $handler_id = NULL) {
  // @phpstan-ignore-next-line
  $schedule_limit = drush_get_option('schedule_limit') ?: 1000;
  // @phpstan-ignore-next-line
  $send_limit = drush_get_option('send_limit') ?: 500;

  // Get and validate optional $webform_id parameter.
  $webform = NULL;
  if ($webform_id) {
    $webform = Webform::load($webform_id);
    if (!$webform) {
      // @phpstan-ignore-next-line
      return drush_set_error(dt('Webform @id not recognized.', ['@id' => $webform_id]));
    }
  }

  // Get and validate optional $handler_id parameter.
  if ($handler_id) {
    try {
      $handler = $webform->getHandler($handler_id);
    }
    catch (\Exception $exception) {
      // @phpstan-ignore-next-line
      return drush_set_error(dt('Handler @id not recognized.', ['@id' => $handler_id]));
    }
    if (!($handler instanceof ScheduleEmailWebformHandler)) {
      // @phpstan-ignore-next-line
      return drush_set_error(dt('Handler @id is not a scheduled email handler.', ['@id' => $handler_id]));
    }
  }

  $webform = ($webform_id) ? Webform::load($webform_id) : NULL;
  /** @var \Drupal\webform_scheduled_email\WebformScheduledEmailManagerInterface $webform_scheduled_email_manager */
  $webform_scheduled_email_manager = \Drupal::service('webform_scheduled_email.manager');
  $stats = $webform_scheduled_email_manager->cron($webform, $handler_id, $schedule_limit, $send_limit);
  Drush::output()->writeln(dt($stats['_message'], $stats['_context']));
}
