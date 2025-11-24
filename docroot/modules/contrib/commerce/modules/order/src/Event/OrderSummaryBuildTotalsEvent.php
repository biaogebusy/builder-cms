<?php

namespace Drupal\commerce_order\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Defines the order summary build totals event.
 *
 * @see \Drupal\commerce_order\Event\OrderEvents
 */
class OrderSummaryBuildTotalsEvent extends EventBase {

  /**
   * Constructs a new OrderSummaryBuildTotalsEvent.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param array $totals
   *   The totals array, containing the subtotal price, adjustments and
   *   total price.
   */
  public function __construct(
    protected OrderInterface $order,
    protected array $totals,
  ) {}

  /**
   * Gets the order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  public function getOrder(): OrderInterface {
    return $this->order;
  }

  /**
   * Gets the totals array.
   *
   * @return array
   *   The totals array.
   */
  public function getTotals(): array {
    return $this->totals;
  }

  /**
   * Sets the totals array.
   *
   * @param array $totals
   *   The totals array.
   */
  public function setTotals(array $totals): void {
    $this->totals = $totals;
  }

}
