<?php

namespace Drupal\workspace\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\workspace\Controller\ReplicationConfigController;
use Symfony\Component\Routing\RouteCollection;

/**
 * ReplicationConfigRouteSubscriber class.
 */
class ReplicationConfigRouteSubscriber extends RouteSubscriberBase {

  /**
   * Alters the replication config route.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('replication.settings_form')) {
      $route->setDefault('_controller', ReplicationConfigController::class . '::getForms');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', 100];
    return $events;
  }

}
