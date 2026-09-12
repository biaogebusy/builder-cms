<?php

namespace Drupal\commerce_log\EventSubscriber;

use Drupal\commerce_log\LogStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_cart\Event\CartOrderItemRemoveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CartEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new CartEventSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [
      CartEvents::CART_ENTITY_ADD => ['onCartEntityAdd', -100],
      CartEvents::CART_ORDER_ITEM_REMOVE => ['onCartOrderItemRemove', -100],
    ];
    return $events;
  }

  /**
   * Creates a log when an entity has been added to the cart.
   *
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   *   The cart event.
   */
  public function onCartEntityAdd(CartEntityAddEvent $event): void {
    $cart = $event->getCart();
    $log_storage = $this->entityTypeManager->getStorage('commerce_log');
    assert($log_storage instanceof LogStorageInterface);
    $log_storage->generate($cart, 'cart_entity_added', [
      'purchased_entity_label' => $event->getOrderItem()->label(),
    ])->save();
  }

  /**
   * Creates a log when an order item has been removed from the cart.
   *
   * @param \Drupal\commerce_cart\Event\CartOrderItemRemoveEvent $event
   *   The cart event.
   */
  public function onCartOrderItemRemove(CartOrderItemRemoveEvent $event): void {
    $cart = $event->getCart();
    $log_storage = $this->entityTypeManager->getStorage('commerce_log');
    assert($log_storage instanceof LogStorageInterface);
    $log_storage->generate($cart, 'cart_item_removed', [
      'purchased_entity_label' => $event->getOrderItem()->label(),
    ])->save();
  }

}
