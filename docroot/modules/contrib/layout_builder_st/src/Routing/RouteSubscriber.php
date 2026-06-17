<?php

namespace Drupal\layout_builder_st\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
final class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($collection->all() as $route) {
      // Add the _layout_builder_translation_access requirement to all routes
      // that have the _layout_builder_access requirement.
      if ($route->getRequirement('_layout_builder_access') === 'view' && !$route->hasRequirement('_layout_builder_translation_access')) {
        $route->setRequirement('_layout_builder_translation_access', 'untranslated');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Come before \Drupal\layout_builder\Routing\LayoutBuilderRoutes. So that
    // only routes provide by layout_builder.routes.yml are altered.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', 100];
    return $events;
  }

}
