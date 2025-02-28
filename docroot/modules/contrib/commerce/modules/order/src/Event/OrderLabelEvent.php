<?php

namespace Drupal\commerce_order\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Defines the order label event.
 *
 * @see \Drupal\commerce_order\Event\OrderEvents
 */
class OrderLabelEvent extends EventBase {

  /**
   * The order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The label.
   *
   * @var string|null
   */
  protected $label;

  /**
   * Constructs a new OrderLabelEvent object.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string|null $label
   *   The order label.
   */
  public function __construct(OrderInterface $order, ?string $label) {
    $this->order = $order;
    $this->label = $label;
  }

  /**
   * Gets the order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  public function getOrder() {
    return $this->order;
  }

  /**
   * Gets the order label.
   *
   * @return string|null
   *   The order label.
   */
  public function getLabel(): ?string {
    return $this->label;
  }

  /**
   * Sets the order label.
   *
   * @param string|null $label
   *   The order label.
   *
   * @return $this
   */
  public function setLabel(?string $label) {
    $this->label = $label;
    return $this;
  }

}
