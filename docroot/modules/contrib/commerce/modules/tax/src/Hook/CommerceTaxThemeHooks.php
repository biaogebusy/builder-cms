<?php

namespace Drupal\commerce_tax\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook implementations for Commerce Tax.
 */
class CommerceTaxThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'commerce_tax_resources' => [
        'variables' => [],
      ],
    ];
  }

}
