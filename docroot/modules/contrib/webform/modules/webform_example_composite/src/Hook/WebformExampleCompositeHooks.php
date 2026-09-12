<?php

namespace Drupal\webform_example_composite\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_example_composite.
 */
class WebformExampleCompositeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    return ['webform_example_composite' => ['render element' => 'element']];
  }

}
