<?php

namespace Drupal\fpa\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $route = $collection->get('user.admin_permissions');
    if ($route) {
      $route->setDefaults([
        '_title' => 'Permissions',
        '_controller' => '\Drupal\fpa\Controller\FPAController::permissionsList',
      ]);
    }
  }

}
