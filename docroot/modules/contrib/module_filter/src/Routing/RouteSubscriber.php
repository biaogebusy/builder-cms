<?php

namespace Drupal\module_filter\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('update.status')) {
      $route->setDefault('_controller', 'Drupal\module_filter\Controller\ModuleFilterUpdateController::updateStatus');
    }
  }

}
