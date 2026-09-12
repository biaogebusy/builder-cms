<?php

namespace Drupal\commerce_cart\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Theme hook implementations for Commerce Cart.
 */
class CommerceCartThemeHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'commerce_cart_block' => [
        'variables' => [
          'icon' => NULL,
          'count' => NULL,
          'count_text' => '',
          'content' => NULL,
          'url' => NULL,
          'links' => [],
          'dropdown' => FALSE,
        ],
        'initial preprocess' => static::class . ':preprocessCommerceCartBlock',
      ],
      'commerce_cart_empty_page' => [
        'render element' => 'element',
      ],
    ];
  }

  /**
   * Implements hook_preprocess_HOOK() for 'commerce_order'.
   */
  #[Hook('preprocess_commerce_order')]
  public function preprocessCommerceOrder(array &$variables): void {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $variables['elements']['#commerce_order'];
    if (isset($variables['order']['cart'], $variables['order_info_fields'])) {
      $variables['order_info_fields']['cart'] = $variables['order']['cart'];
      $variables['order_info_fields']['cart']['#weight'] = $variables['order_info_fields']['store_id']['#weight'] + 1;
      $variables['order_info_fields']['cart']['#title'] = $this->t('Is cart');
      $variables['order_info_fields']['cart']['#attributes']['class'][] = 'form-item';
    }
    if (isset($variables['additional_order_fields'])) {
      unset($variables['additional_order_fields']['cart']);
    }
    $variables['is_cart'] = ($order->hasField('cart') && $order->get('cart')->value);
  }

  /**
   * Implements hook_preprocess_HOOK() for 'views_view'.
   */
  #[Hook('preprocess_views_view')]
  public function preprocessViewsView(array &$variables): void {
    $view = $variables['view'];
    if (str_contains($view->storage->get('tag'), 'commerce_cart_form')) {
      // Moves the commerce_cart_form footer output above the submit buttons.
      $variables['rows']['footer'] = $variables['footer'];
      $variables['footer'] = '';
    }
  }

  /**
   * Prepares variables for the cart block element template.
   */
  public function preprocessCommerceCartBlock(array &$variables): void {
    $variables['attributes']['class'][] = 'cart--cart-block';
  }

}
