<?php

namespace Drupal\webform_test_handler\Hook;

use Drupal\webform\WebformSubmissionInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_test_handler.
 */
class WebformTestHandlerHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    return [
      'webform_handler_test_summary' => [
        'variables' => [
          'settings' => NULL,
          'handler' => [],
        ],
      ],
      'webform_handler_test_offcanvas_width' => [
        'variables' => [
          'settings' => NULL,
          'handler' => [],
        ],
      ],
    ];
  }

  /**
   * Implements hook_webform_submissions_pre_purge().
   */
  #[Hook('webform_submissions_pre_purge')]
  public function webformSubmissionsPrePurge(array &$webform_submissions) {
    array_shift($webform_submissions);
    \Drupal::state()->set('webform_test_purge_hook_pre', array_map(function (WebformSubmissionInterface $submission) {
        return $submission->id();
    }, $webform_submissions));
  }

  /**
   * Implements hook_webform_submissions_post_purge().
   */
  #[Hook('webform_submissions_post_purge')]
  public function webformSubmissionsPostPurge(array $webform_submissions) {
    \Drupal::state()->set('webform_test_purge_hook_post', array_map(function (WebformSubmissionInterface $submission) {
        return $submission->id();
    }, $webform_submissions));
  }

}
