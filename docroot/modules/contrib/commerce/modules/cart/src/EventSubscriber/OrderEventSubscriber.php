<?php

namespace Drupal\commerce_cart\EventSubscriber;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new OrderEventSubscriber object.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cartProvider
   *   The cart provider.
   */
  public function __construct(protected CartProviderInterface $cartProvider) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [
      // We don't rely on the constant defining the checkout completion event
      // because commerce_cart doesn't depend on commerce_checkout.
      // Note that the priority is set to 200 to ensure the cart logic
      // finalizing the cart runs before the guest checkout completion event
      // subscriber defined by the commerce_checkout module to ensure the cart
      // ID is moved to the "completed" cart session before the order is
      // assigned to the customer being created by the checkout subscriber.
      'commerce_checkout.completion' => ['onCheckoutCompletion', 200],
      'commerce_order.place.pre_transition' => 'finalizeCart',
    ];
    return $events;
  }

  /**
   * Finalizes the cart when the order is placed.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event.
   */
  public function finalizeCart(WorkflowTransitionEvent $event) {
    $order = $event->getEntity();
    if (!empty($order->cart->value)) {
      $this->cartProvider->finalizeCart($order, FALSE);
    }
  }

  /**
   * Finalizes the cart on checkout completion.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event.
   */
  public function onCheckoutCompletion(OrderEvent $event) {
    $order = $event->getOrder();
    if (!empty($order->cart->value)) {
      $this->cartProvider->finalizeCart($order, FALSE);
    }
  }

}
