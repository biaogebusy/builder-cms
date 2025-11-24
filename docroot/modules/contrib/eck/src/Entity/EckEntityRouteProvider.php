<?php

namespace Drupal\eck\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\EntityRouteProviderInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for eck entities.
 */
class EckEntityRouteProvider implements EntityRouteProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $route_collection = new RouteCollection();

    if ($eck_type = EckEntityType::load($entity_type->id())) {
      $view_defaults = [
        '_entity_view' => $eck_type->id(),
        '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::title',
      ];
      $route_view = new Route("{$eck_type->id()}/{{$eck_type->id()}}");
      $route_view->addDefaults($view_defaults);
      $route_view->setRequirement('_entity_access', $eck_type->id() . '.view');
      $route_collection->add("entity.{$eck_type->id()}.canonical", $route_view);

      $edit_defaults = [
        '_entity_form' => $eck_type->id() . '.edit',
        '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::editTitle',
      ];
      $route_edit = new Route("{$eck_type->id()}/{{$eck_type->id()}}/edit");
      $route_edit->addDefaults($edit_defaults);
      $route_edit->setRequirement('_entity_access', $eck_type->id() . '.edit');
      $route_edit->setOption('_eck_operation_route', TRUE);
      $route_collection->add("entity.{$eck_type->id()}.edit_form", $route_edit);

      // Route for delete.
      $delete_defaults = [
        '_entity_form' => $eck_type->id() . '.delete',
        '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::editTitle',
      ];
      $route_delete = new Route("{$eck_type->id()}/{{$eck_type->id()}}/delete");
      $route_delete->addDefaults($delete_defaults);
      $route_delete->setRequirement('_entity_access', $eck_type->id() . '.delete');
      $route_delete->setOption('_eck_operation_route', TRUE);
      $route_collection->add("entity.{$eck_type->id()}.delete_form", $route_delete);
    }

    return $route_collection;
  }

}
