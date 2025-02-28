<?php

namespace Drupal\menu_item_extras\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\menu_item_extras\Controller\MenuController;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class for RouteSubscriber.
 *
 * @package Drupal\menu_item_extras\Routing
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $route = $collection->get('entity.menu.add_link_form');
    if ($route) {
      $route->setDefault('_controller', MenuController::class . '::addLink');
    }
  }

}
