<?php

namespace Drupal\entity\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\EntityRouteProviderInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides the HTML route for deleting multiple entities.
 *
 * @deprecated Since Drupal 8.6.x the core DefaultHtmlRouteProvider provides
 *   the route for any entity type with a "delete-multiple-form" link template
 *   and a "delete-multiple-confirm" form.
 */
class DeleteMultipleRouteProvider implements EntityRouteProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $routes = new RouteCollection();
    if ($route = $this->deleteMultipleFormRoute($entity_type)) {
      $routes->add('entity.' . $entity_type->id() . '.delete_multiple_form', $route);
    }

    return $routes;
  }

  /**
   * Returns the delete multiple form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function deleteMultipleFormRoute(EntityTypeInterface $entity_type) {
    // Core requires a "delete-multiple-confirm" form to be declared as well,
    // if it's missing, it's safe to assume that the entity type is still
    // relying on previous Entity API contrib behavior.
    if ($entity_type->hasLinkTemplate('delete-multiple-form') && !$entity_type->hasHandlerClass('form', 'delete-multiple-confirm')) {
      $route = new Route($entity_type->getLinkTemplate('delete-multiple-form'));
      $route->setDefault('_form', '\Drupal\entity\Form\DeleteMultipleForm');
      $route->setDefault('entity_type_id', $entity_type->id());
      $route->setRequirement('_entity_delete_multiple_access', $entity_type->id());

      return $route;
    }
  }

}
