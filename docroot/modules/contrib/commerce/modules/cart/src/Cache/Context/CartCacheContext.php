<?php

namespace Drupal\commerce_cart\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\commerce_cart\CartProviderInterface;

/**
 * Defines the CartCacheContext service, for "per cart" caching.
 *
 * Cache context ID: 'cart'.
 */
class CartCacheContext implements CacheContextInterface {

  /**
   * Constructs a new CartCacheContext object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\commerce_cart\CartProviderInterface $cartProvider
   *   The cart provider service.
   */
  public function __construct(protected AccountInterface $account, protected CartProviderInterface $cartProvider) {}

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Current cart IDs');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return implode(':', $this->cartProvider->getCartIds($this->account));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    $metadata = new CacheableMetadata();
    foreach ($this->cartProvider->getCarts($this->account) as $cart) {
      $metadata->addCacheableDependency($cart);
    }
    return $metadata;
  }

}
