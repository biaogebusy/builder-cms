<?php

namespace Drupal\core_context\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Modifies certain canonical entity routes to expose contexts.
 */
final class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $route = $collection->get('entity.node.canonical');
    if ($route) {
      $route->setDefault('_core_context_entity', 'node.full');
    }
  }

}
