<?php

namespace Drupal\commerce_promotion\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;

/**
 * Theme hook implementations for Commerce Promotion.
 */
class CommercePromotionThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'commerce_promotion' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessCommercePromotion',
      ],
      'commerce_promotion_form' => [
        'render element' => 'form',
      ],
      'commerce_coupon_redemption_form' => [
        'render element' => 'form',
      ],
    ];
  }

  /**
   * Prepares variables for promotion templates.
   *
   * Default template: commerce-promotion.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An associative array containing rendered fields.
   *   - attributes: HTML attributes for the containing element.
   */
  public function preprocessCommercePromotion(array &$variables): void {
    /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
    $promotion = $variables['elements']['#commerce_promotion'];

    $variables['promotion_entity'] = $promotion;
    $variables['promotion_url'] = $promotion->isNew() ? '' : $promotion->toUrl();
    $variables['promotion'] = [];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['promotion'][$key] = $variables['elements'][$key];
    }
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_commerce_promotion')]
  public function themeSuggestionsCommercePromotion(array $variables): array {
    return _commerce_entity_theme_suggestions('commerce_promotion', $variables);
  }

}
