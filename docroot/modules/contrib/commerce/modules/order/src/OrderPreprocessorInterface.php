<?php

namespace Drupal\commerce_order;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Defines an interface for order preprocessors.
 *
 * Order preprocessors are responsible for resetting an order to an unprocessed
 * state prior to processing, e.g. to revert changes to order items made by
 * processors as in the case of a Buy X, Get Y promotion.
 */
interface OrderPreprocessorInterface {

  /**
   * Preprocesses an order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  public function preprocess(OrderInterface $order);

}
