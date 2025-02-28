<?php

namespace Drupal\migrate_tools\Routing;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\RouteProcessor\OutboundRouteProcessorInterface;
use Symfony\Component\Routing\Route;

/**
 * Route processor to expand migrate_group.
 */
class RouteProcessor implements OutboundRouteProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processOutbound($route_name, Route $route, array &$parameters, BubbleableMetadata $bubbleable_metadata = NULL) {
    if ($route->hasDefault('_migrate_group')) {
      if ($migration = \Drupal::entityTypeManager()->getStorage('migration')->load($parameters['migration'])) {
        if ($group = $migration->get('migration_group')) {
          $parameters['migration_group'] = $group;
        }
      }
    }
  }

}
