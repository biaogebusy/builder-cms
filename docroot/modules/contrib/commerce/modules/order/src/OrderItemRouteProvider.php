<?php

namespace Drupal\commerce_order;

use Drupal\commerce_order\Controller\OrderItemController;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for the Order item entity.
 */
class OrderItemRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getEditFormRoute(EntityTypeInterface $entity_type): ?Route {
    $route = parent::getEditFormRoute($entity_type);
    if (!$route) {
      return NULL;
    }

    $route->setDefault('_title_callback', OrderItemController::class . '::editTitle');
    $route->setOption('parameters', [
      'commerce_order' => [
        'type' => 'entity:commerce_order',
      ],
      'commerce_order_item' => [
        'type' => 'entity:commerce_order_item',
      ],
    ]);
    $route->setOption('_admin_route', TRUE);

    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeleteFormRoute(EntityTypeInterface $entity_type): ?Route {
    $route = parent::getDeleteFormRoute($entity_type);
    if (!$route) {
      return NULL;
    }
    $route->setOption('parameters', [
      'commerce_order' => [
        'type' => 'entity:commerce_order',
      ],
      'commerce_order_item' => [
        'type' => 'entity:commerce_order_item',
      ],
    ]);
    $route->setOption('_admin_route', TRUE);

    return $route;
  }

}
