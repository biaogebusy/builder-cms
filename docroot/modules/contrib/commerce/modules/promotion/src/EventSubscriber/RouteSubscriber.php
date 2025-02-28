<?php

namespace Drupal\commerce_promotion\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides a route subscriber that adding the _admin_route option
 * to the routes like "promotion/%/coupons".
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    // Ensure to run after the Views route subscriber.
    // @see \Drupal\views\EventSubscriber\RouteSubscriber.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -200];

    return $events;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $route = $collection->get('entity.commerce_promotion_coupon.collection');
    if ($route) {
      $route->setOption('_admin_route', TRUE);
      $route->setOption('parameters', [
        'commerce_promotion' => [
          'type' => 'entity:commerce_promotion',
        ],
      ]);

      // Coupons can be created if the parent promotion can be updated so they
      // should be able to access the canonical page.
      // We need alter after Views to ensure the View access doesn't override
      // our change.
      $requirements = $route->getRequirements();
      if (isset($requirements['_permission'])) {
        // Unset the _permission access handler in case Views is not installed.
        unset($requirements['_permission']);
      }
      if (isset($requirements['_access'])) {
        // Unset the unrestricted access in case Views overrode it.
        unset($requirements['_access']);
      }
      $requirements['_entity_access'] = 'commerce_promotion.update';
      $route->setRequirements($requirements);
    }
  }

}
