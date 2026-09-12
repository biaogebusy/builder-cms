<?php

namespace Drupal\webform_scheduled_email\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_scheduled_email.
 */
class WebformScheduledEmailDrushHooks {

  /**
   * Implements hook_drush_command().
   */
  #[Hook('drush_command')]
  public function drushCommand() {
    $items = [];
    /* Submissions */
    $items['webform-scheduled-email-cron'] = [
      'description' => 'Executes cron task for webform scheduled emails.',
      'core' => [
        '8+',
      ],
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
      'arguments' => [
        'webform_id' => '(optional) The webform ID you want the cron task to be executed for',
        'handler_id' => '(optional) The handler ID you want the cron task to be executed for',
      ],
      'options' => [
        'schedule_limit' => 'The maximum number of emails to be scheduled. If set to 0 no emails will be scheduled. (Default 1000)',
        'send_limit' => 'The maximum number of emails to be sent. If set to 0 no emails will be sent. (Default 500)',
      ],
      'callback' => 'webform_scheduled_email_cron_process',
      'aliases' => [
        'wfsec',
        'webform:scheduled-email:cron',
      ],
    ];
    return $items;
  }

}
