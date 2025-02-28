<?php

namespace Drupal\entity\Routing;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Provides HTML routes for entities with administrative add/edit/delete pages.
 *
 * Use this class if the add/edit/delete form routes should use the
 * administrative theme.
 */
class AdminHtmlRouteProvider extends DefaultHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);

    $entity_type_id = $entity_type->id();
    $admin_route_names = [
      "entity.{$entity_type_id}.add_page",
      "entity.{$entity_type_id}.add_form",
      "entity.{$entity_type_id}.edit_form",
      "entity.{$entity_type_id}.delete_form",
      "entity.{$entity_type_id}.delete_multiple_form",
      "entity.{$entity_type_id}.duplicate_form",
    ];
    foreach ($admin_route_names as $admin_route_name) {
      if ($route = $collection->get($admin_route_name)) {
        $route->setOption('_admin_route', TRUE);
      }
    }

    return $collection;
  }

}
