<?php

namespace Drupal\commerce_order\Access;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\Routing\Route;

/**
 * Access check that checks whether the order is in a defined state.
 */
class OrderStateAccessCheck implements AccessInterface {

  /**
   * Checks the order state.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function access(Route $route, RouteMatchInterface $route_match): AccessResultInterface {
    $order = $route_match->getParameter('commerce_order');

    if (!$order instanceof OrderInterface) {
      return AccessResult::neutral();
    }

    $states = $route->getRequirement('_order_state');
    if ($states === NULL) {
      return AccessResult::neutral();
    }
    $states = array_map('trim', explode(',', $states));

    return AccessResult::allowedIf(in_array($order->getState()->getId(), $states, TRUE))
      ->addCacheableDependency($order);
  }

}
