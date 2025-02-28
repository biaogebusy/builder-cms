<?php

namespace Drupal\commerce_cart\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;

/**
 * Defines the cart order item add event.
 *
 * @see \Drupal\commerce_cart\Event\CartEvents
 */
class CartOrderItemAddEvent extends EventBase {

  /**
   * The cart order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $cart;

  /**
   * The quantity.
   *
   * @var float
   */
  protected $quantity;

  /**
   * The added order item.
   *
   * @var \Drupal\commerce_order\Entity\OrderItemInterface
   */
  protected $orderItem;

  /**
   * Constructs a new CartOrderItemRemoveEvent.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $cart
   *   The cart order.
   * @param float $quantity
   *   The quantity.
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The added order item.
   */
  public function __construct(OrderInterface $cart, $quantity, OrderItemInterface $order_item) {
    $this->cart = $cart;
    $this->quantity = $quantity;
    $this->orderItem = $order_item;
  }

  /**
   * Gets the cart order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The cart order.
   */
  public function getCart() {
    return $this->cart;
  }

  /**
   * Gets the quantity.
   *
   * @return float
   *   The quantity.
   */
  public function getQuantity() {
    return $this->quantity;
  }

  /**
   * Gets the added order item.
   *
   * @return \Drupal\commerce_order\Entity\OrderItemInterface
   *   The order item entity.
   */
  public function getOrderItem() {
    return $this->orderItem;
  }

}
