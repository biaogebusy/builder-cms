<?php

namespace Drupal\webform_options_limit\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_options_limit.
 */
class WebformOptionsLimitHooks {
  use StringTranslationTrait;

  /**
   * @file
   * Allows elements with options (i.e. select, checkboxes, and radios) to have option specific submission limits.
   */

  /**
   * Implements hook_webform_help_info().
   */
  #[Hook('webform_help_info')]
  public function webformHelpInfo() {
    $help = [];
    $help['webform_options_limit'] = [
      'group' => 'forms',
      'title' => $this->t('Options limit'),
      'content' => $this->t("The <strong>Options</strong> page displays a summary of the webform's options limits."),
      'routes' => [
              // @see /admin/structure/webform/manage/{webform}/results/options-limit
        'entity.webform_options_limit.summary',
              // @see /node/{node}/webform/results/options-limit
        'entity.node.webform_options_limit.summary',
      ],
    ];
    return $help;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    return [
      'webform_handler_options_limit_summary' => [
        'variables' => [
          'settings' => NULL,
          'handler' => [],
        ],
      ],
    ];
  }

  /**
   * Implements hook_local_tasks_alter().
   */
  #[Hook('local_tasks_alter')]
  public function localTasksAlter(&$local_tasks) {
    // Remove webform node results import if the webform_node.module
    // is not installed.
    if (!\Drupal::moduleHandler()->moduleExists('webform_node')) {
      unset($local_tasks['entity.node.webform_options_limit.summary']);
    }
  }

}
