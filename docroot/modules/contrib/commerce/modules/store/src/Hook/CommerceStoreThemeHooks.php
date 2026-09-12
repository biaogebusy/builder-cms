<?php

namespace Drupal\commerce_store\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;

/**
 * Theme hook implementations for Commerce Store.
 */
class CommerceStoreThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'commerce_store' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessCommerceStore',

      ],
      'commerce_store_form' => [
        'render element' => 'form',
      ],
    ];
  }

  /**
   * Prepares variables for store templates.
   *
   * Default template: commerce-store.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An associative array containing rendered fields.
   *   - attributes: HTML attributes for the containing element.
   */
  public function preprocessCommerceStore(array &$variables): void {
    /** @var \Drupal\commerce_store\Entity\StoreInterface $store */
    $store = $variables['elements']['#commerce_store'];

    $variables['store_entity'] = $store;
    $variables['store_url'] = $store->isNew() ? '' : $store->toUrl();
    $variables['store'] = [];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['store'][$key] = $variables['elements'][$key];
    }
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_commerce_store')]
  public function themeSuggestionsCommerceStore(array $variables): array {
    return _commerce_entity_theme_suggestions('commerce_store', $variables);
  }

}
