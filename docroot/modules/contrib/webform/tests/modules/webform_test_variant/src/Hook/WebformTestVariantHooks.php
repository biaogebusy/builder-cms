<?php

namespace Drupal\webform_test_variant\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_test_variant.
 */
class WebformTestVariantHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    return [
      'webform_variant_test_summary' => [
        'variables' => [
          'settings' => NULL,
          'variant' => [],
        ],
      ],
      'webform_variant_test_offcanvas_width' => [
        'variables' => [
          'settings' => NULL,
          'variant' => [],
        ],
      ],
    ];
  }

}
