<?php

namespace Drupal\commerce_cart;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Provides #lazy_builder callbacks.
 */
class CartLazyBuilders implements TrustedCallbackInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new CartLazyBuilders object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\commerce_cart\CartProviderInterface $cartProvider
   *   The cart provider.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CartProviderInterface $cartProvider,
    protected ModuleExtensionList $moduleExtensionList,
  ) {
  }

  /**
   * The #lazy_builder callback; replaces placeholder with message.
   *
   * @param bool $dropdown
   *   Whether to render a dropdown.
   * @param bool $show_if_empty
   *   Whether to show if empty.
   *
   * @return array
   *   A render array containing the message.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function cartBlock(bool $dropdown, bool $show_if_empty): array {
    $cacheable_metadata = new CacheableMetadata();
    $cacheable_metadata->addCacheContexts(['user', 'session', 'cart']);
    $cart_cache_tags = [];

    $carts = $this->cartProvider->getCarts() ?? [];
    foreach ($carts as $cart) {
      // Add tags for all carts regardless items or cart flag.
      $cart_cache_tags = Cache::mergeTags($cart_cache_tags, $cart->getCacheTags());
    }
    $cacheable_metadata->addCacheTags($cart_cache_tags);

    $carts = array_filter($carts, static function ($cart) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $cart */
      // There is a chance the cart may have converted from a draft order, but
      // is still in session. Such as just completing check out. So we verify
      // that the cart is still a cart.
      return $cart->hasItems() && $cart->get('cart')->value;
    });

    $count = 0;
    $cart_views = [];
    if (!empty($carts)) {
      $cart_views = $this->getCartViews($carts, $dropdown);
      foreach ($carts as $cart) {
        foreach ($cart->getItems() as $order_item) {
          $count += (int) $order_item->getQuantity();
        }
        $cacheable_metadata->addCacheableDependency($cart);
      }
    }

    $links = [];
    $links[] = [
      '#type' => 'link',
      '#title' => $this->t('Cart'),
      '#url' => Url::fromRoute('commerce_cart.page'),
    ];

    if (!$show_if_empty && $count === 0) {
      return [
        '#cache' => [
          'contexts' => ['cart'],
        ],
      ];
    }

    return [
      '#attached' => [
        'library' => ['commerce_cart/cart_block'],
      ],
      '#theme' => 'commerce_cart_block',
      '#icon' => [
        '#theme' => 'image',
        '#uri' => $this->moduleExtensionList->getPath('commerce') . '/icons/ffffff/cart.png',
        '#alt' => $this->t('Shopping cart'),
      ],
      '#count' => $count,
      '#count_text' => $this->formatPlural($count, '@count item', '@count items'),
      '#url' => Url::fromRoute('commerce_cart.page')->toString(),
      '#content' => $cart_views,
      '#links' => $links,
      '#cache' => [
        'contexts' => ['cart'],
      ],
      '#dropdown' => $dropdown,
    ];
  }

  /**
   * Gets the cart views for each cart.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface[] $carts
   *   The cart orders.
   * @param bool $dropdown
   *   Whether to render a dropdown.
   *
   * @return array
   *   An array of view ids keyed by cart order ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getCartViews(array $carts, bool $dropdown): array {
    $cart_views = [];
    if ($dropdown) {
      $order_type_ids = array_map(static function ($cart) {
        return $cart->bundle();
      }, $carts);
      $order_type_storage = $this->entityTypeManager->getStorage('commerce_order_type');
      $order_types = $order_type_storage->loadMultiple(array_unique($order_type_ids));

      $available_views = [];
      foreach ($order_type_ids as $cart_id => $order_type_id) {
        /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
        $order_type = $order_types[$order_type_id];
        $available_views[$cart_id] = $order_type->getThirdPartySetting('commerce_cart', 'cart_block_view', 'commerce_cart_block');
      }

      foreach ($carts as $cart_id => $cart) {
        $cart_views[] = [
          '#prefix' => '<div class="cart cart-block">',
          '#suffix' => '</div>',
          '#type' => 'view',
          '#name' => $available_views[$cart_id],
          '#arguments' => [$cart_id],
          '#embed' => TRUE,
        ];
      }
    }
    return $cart_views;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['cartBlock'];
  }

}
