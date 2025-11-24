<?php

namespace Drupal\commerce_cart\EventSubscriber;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\entity\QueryAccess\QueryAccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class QueryAccessSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new QueryAccessSubscriber object.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cartProvider
   *   The cart provider.
   * @param \Drupal\commerce_cart\CartSessionInterface $cartSession
   *   The cart session.
   */
  public function __construct(protected CartProviderInterface $cartProvider, protected CartSessionInterface $cartSession) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'entity.query_access.commerce_order' => 'onQueryAccess',
    ];
  }

  /**
   * Modifies the access conditions for cart orders.
   *
   * @param \Drupal\entity\QueryAccess\QueryAccessEvent $event
   *   The event.
   */
  public function onQueryAccess(QueryAccessEvent $event) {
    if ($event->getOperation() != 'view') {
      return;
    }

    $conditions = $event->getConditions();
    // The user already has full access due to a "administer commerce_order"
    // or "view commerce_order" permission.
    if (!$conditions->count() && !$conditions->isAlwaysFalse()) {
      return;
    }

    $account = $event->getAccount();
    // Any user can view their own active carts, regardless of any permissions.
    $cart_ids = $this->cartProvider->getCartIds($account);
    if ($account->isAnonymous()) {
      $completed_cart_ids = $this->cartSession->getCartIds(CartSessionInterface::COMPLETED);
      $cart_ids = array_merge($cart_ids, $completed_cart_ids);
    }

    if (!empty($cart_ids)) {
      $conditions->addCondition('order_id', $cart_ids);
      $conditions->alwaysFalse(FALSE);
    }
  }

}
