<?php

namespace Drupal\commerce\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to routing events.
 */
class RouteSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new RouteSubscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manger.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Should run after RouteSubscriber defined in the Views module.
    $events[RoutingEvents::ALTER][] = [
      'addCommerceEntityRouteParameters',
      -200,
    ];
    return $events;
  }

  /**
   * Enables entity upcasting for commerce entities on Views routes.
   *
   * Core does not upcast Views route parameters. This adds the "parameters"
   * option so a placeholder named after a commerce entity type (e.g.
   * %commerce_order) is converted to the loaded entity.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   The route build event.
   */
  public function addCommerceEntityRouteParameters(RouteBuildEvent $event): void {
    foreach ($event->getRouteCollection() as $route) {
      // Only Views routes carry "view_id" and the argument map.
      if (!$route->getDefault('view_id')
        || empty($argument_map = $route->getOption('_view_argument_map'))
      ) {
        continue;
      }

      // Map values are the placeholder names; upcast the commerce ones only.
      foreach ($argument_map as $parameter) {
        if (!str_starts_with($parameter, 'commerce_')
          || !$this->entityTypeManager->hasDefinition($parameter)
        ) {
          continue;
        }

        $parameters = $route->getOption('parameters') ?: [];
        $parameters[$parameter] = ['type' => 'entity:' . $parameter];
        $route->setOption('parameters', $parameters);
      }
    }
  }

}
