<?php

namespace Drupal\commerce_order;

use Drupal\commerce\Context;
use Drupal\commerce_order\Entity\OrderItemInterface;

/**
 * Default implementation of the availability manager.
 */
class AvailabilityManager implements AvailabilityManagerInterface {

  /**
   * The checkers.
   *
   * @var \Drupal\commerce_order\AvailabilityCheckerInterface[]
   */
  protected $checkers = [];

  /**
   * {@inheritdoc}
   */
  public function addChecker(AvailabilityCheckerInterface $checker) {
    $this->checkers[] = $checker;
  }

  /**
   * {@inheritdoc}
   */
  public function check(OrderItemInterface $order_item, Context $context) : AvailabilityResult {
    foreach ($this->checkers as $checker) {
      if (!$checker->applies($order_item)) {
        continue;
      }
      $result = $checker->check($order_item, $context);
      if ($result instanceof AvailabilityResult && $result->isUnavailable()) {
        return $result;
      }
    }

    return AvailabilityResult::neutral();
  }

}
