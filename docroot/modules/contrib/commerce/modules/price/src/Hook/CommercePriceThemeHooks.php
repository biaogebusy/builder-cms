<?php

namespace Drupal\commerce_price\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook implementations for Commerce Price.
 */
class CommercePriceThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'commerce_price_plain' => [
        'variables' => [
          'number' => 0,
          'currency' => NULL,
        ],
        'template' => 'commerce-price-plain',
      ],
      'commerce_price_calculated' => [
        'variables' => [
          'calculated_price' => NULL,
          'purchasable_entity' => NULL,
        ],
      ],
    ];
  }

}
