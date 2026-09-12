<?php

namespace Drupal\eck\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\EntityRouteProviderInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides routes for eck entities.
 */
class EckEntityRouteProvider implements EntityRouteProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $route_collection = new RouteCollection();

    if (!$eck_type = EckEntityType::load($entity_type->id())) {
      return $route_collection;
    }

    $route_edit = new Route($entity_type->getLinkTemplate('edit-form'));
    $route_edit->setDefault('_entity_form', $eck_type->id() . '.edit');
    $route_edit->setDefault('_title_callback', '\Drupal\Core\Entity\Controller\EntityController::editTitle');
    $route_edit->setRequirement('_entity_access', $eck_type->id() . '.edit');
    $route_edit->setOption('_eck_operation_route', TRUE);
    $route_collection->add("entity.{$eck_type->id()}.edit_form", $route_edit);

    if ($eck_type->hasStandaloneUrl()) {
      $route_view = new Route($entity_type->getLinkTemplate('canonical'));
      $route_view->setDefault('_entity_view', $eck_type->id());
      $route_view->setDefault('_title_callback', '\Drupal\Core\Entity\Controller\EntityController::title');
      $route_view->setRequirement('_entity_access', $eck_type->id() . '.view');
      $route_collection->add("entity.{$eck_type->id()}.canonical", $route_view);
    }
    else {
      $route_collection->add("entity.{$eck_type->id()}.canonical", $route_edit);
    }

    if ($entity_type->hasLinkTemplate('delete-form')) {
      $route_delete = new Route($entity_type->getLinkTemplate('delete-form'));
      $route_delete->setDefault('_entity_form', $eck_type->id() . '.delete');
      $route_delete->setDefault('_title_callback', '\Drupal\Core\Entity\Controller\EntityController::deleteTitle');
      $route_delete->setRequirement('_entity_access', $eck_type->id() . '.delete');
      $route_delete->setOption('_eck_operation_route', TRUE);
      $route_collection->add("entity.{$eck_type->id()}.delete_form", $route_delete);
    }

    return $route_collection;
  }

}
