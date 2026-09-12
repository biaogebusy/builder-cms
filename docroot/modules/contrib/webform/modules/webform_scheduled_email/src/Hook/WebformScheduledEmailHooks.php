<?php

namespace Drupal\webform_scheduled_email\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_scheduled_email.
 */
class WebformScheduledEmailHooks {

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(&$definitions) {
    // Append email handler to scheduled email handler settings.
    if (isset($definitions['webform.handler.email']['mapping']) && isset($definitions['webform.handler.scheduled_email'])) {
      $definitions['webform.handler.scheduled_email']['mapping'] += $definitions['webform.handler.email']['mapping'];
    }
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity) {
    /** @var \Drupal\webform_scheduled_email\WebformScheduledEmailManagerInterface $webform_scheduled_email_manager */
    $webform_scheduled_email_manager = \Drupal::service('webform_scheduled_email.manager');
    $webform_scheduled_email_manager->reschedule($entity);
  }

  /**
   * Implements hook_entity_predelete().
   */
  #[Hook('entity_predelete')]
  public function entityPredelete(EntityInterface $entity) {
    /** @var \Drupal\webform_scheduled_email\WebformScheduledEmailManagerInterface $webform_scheduled_email_manager */
    $webform_scheduled_email_manager = \Drupal::service('webform_scheduled_email.manager');
    $webform_scheduled_email_manager->unschedule($entity);
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron() {
    /** @var \Drupal\webform_scheduled_email\WebformScheduledEmailManagerInterface $webform_scheduled_email_manager */
    $webform_scheduled_email_manager = \Drupal::service('webform_scheduled_email.manager');
    $webform_scheduled_email_manager->cron();
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    return [
      'webform_handler_scheduled_email_summary' => [
        'variables' => [
          'settings' => NULL,
          'handler' => [],
          'status' => NULL,
        ],
      ],
    ];
  }

}
