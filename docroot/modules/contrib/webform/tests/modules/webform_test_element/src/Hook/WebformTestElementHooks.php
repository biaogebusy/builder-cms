<?php

namespace Drupal\webform_test_element\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_test_element.
 */
class WebformTestElementHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme', module: 'webform_test_element_handler')]
  public function handlerTheme() {
    return [
      'webform_handler_test_summary' => [
        'variables' => [
          'settings' => NULL,
          'handler' => [],
        ],
      ],
    ];
  }

}
