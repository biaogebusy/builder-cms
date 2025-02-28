<?php

namespace Drupal\entity\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider as CoreDefaultHtmlRouteProvider;
use Drupal\entity\Controller\EntityDuplicateController;
use Symfony\Component\Routing\Route;

/**
 * Provides HTML routes for entities.
 */
class DefaultHtmlRouteProvider extends CoreDefaultHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);

    $entity_type_id = $entity_type->id();
    if ($duplicate_route = $this->getDuplicateFormRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.duplicate_form", $duplicate_route);
    }

    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    $route = parent::getCollectionRoute($entity_type);
    if ($route && $entity_type->hasHandlerClass('permission_provider')) {
      $admin_permission = $entity_type->getAdminPermission();
      $overview_permission = "access {$entity_type->id()} overview";
      $route->setRequirement('_permission', "$admin_permission+$overview_permission");
    }
    return $route;
  }

  /**
   * Gets the duplicate-form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getDuplicateFormRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('duplicate-form')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('duplicate-form'));
      $route
        ->setDefaults([
          '_controller' => EntityDuplicateController::class . '::form',
          '_title_callback' => EntityDuplicateController::class . '::title',
          'entity_type_id' => $entity_type_id,
        ])
        ->setRequirement('_entity_access', "{$entity_type_id}.duplicate")
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);
      // Entity types with serial IDs can specify this in their route
      // requirements, improving the matching process.
      if ($this->getEntityTypeIdKeyType($entity_type) === 'integer') {
        $route->setRequirement($entity_type_id, '\d+');
      }

      return $route;
    }
  }

}
