<?php

namespace Drupal\commerce_payment\Access;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Defines an access checker for the Payment create route.
 */
class PaymentCreateAccessCheck implements AccessInterface {

  /**
   * Checks access to the Payment creation.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account) {
    $order = $route_match->getParameter('commerce_order');
    assert($order instanceof OrderInterface);
    if (!$order->getBalance() instanceof Price) {
      return AccessResult::forbidden('Order Balance is NULL')->addCacheableDependency($order);
    }
    return AccessResult::allowed()->addCacheableDependency($order);
  }

}
