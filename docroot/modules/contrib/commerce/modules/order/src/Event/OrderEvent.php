<?php

namespace Drupal\commerce_order\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Defines the order event.
 *
 * @see \Drupal\commerce_order\Event\OrderEvents
 */
class OrderEvent extends EventBase {

  /**
   * The order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * Constructs a new OrderEvent.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  public function __construct(OrderInterface $order) {
    $this->order = $order;
  }

  /**
   * Gets the order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   Gets the order.
   */
  public function getOrder() {
    return $this->order;
  }

}
