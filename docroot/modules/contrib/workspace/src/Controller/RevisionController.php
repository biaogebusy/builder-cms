<?php

namespace Drupal\workspace\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\RouteMatchInterface;

class RevisionController extends ControllerBase {

  /**
   * Title callback for view routes.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *    A RouteMatch object.
   *
   * @return array
   *    Array of page elements to render.
   */
  public function viewTitle(RouteMatchInterface $route_match) {
    $parameter_name = $route_match->getRouteObject()->getOption('_entity_type_id');
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity **/
    $entity = $route_match->getParameter($parameter_name);
    return $entity->label();
  }

  /**
   * Renders an entity revision.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *    A RouteMatch object.
   * @return array
   *    Array of page elements to render.
   * @throws \Exception
   */
  public function view(RouteMatchInterface $route_match) {
    $parameter_name = $route_match->getRouteObject()->getOption('_entity_type_id');
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity **/
    $entity = $route_match->getParameter($parameter_name);
    if ($entity && $entity instanceof ContentEntityInterface) {
      /** @var EntityTypeInterface $entity_type */
      $entity_type = $entity->getEntityType();
      return \Drupal::service('entity_type.manager')->getViewBuilder($entity_type->id())->view($entity);
    }
    else {
      throw new \Exception('Invalid entity.');
    }
  }

}
