<?php

namespace Drupal\webform_cards_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_cards_test.
 */
class WebformCardsTestHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    $info = [
      'webform_progress__test_cards_progress_custom' => [
        'variables' => [
          'webform' => NULL,
          'webform_submission' => NULL,
          'current_page' => NULL,
          'operation' => NULL,
          'pages' => [],
        ],
      ],
    ];
    return $info;
  }

}
