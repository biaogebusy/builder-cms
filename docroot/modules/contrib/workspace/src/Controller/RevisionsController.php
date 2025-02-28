<?php

namespace Drupal\workspace\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;

class RevisionsController extends ControllerBase {

  /**
   * Prints the revision tree of the current entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *    A RouteMatch object.
   *
   * @return array
   *    Array of page elements to render.
   */
  public function revisions(RouteMatchInterface $route_match) {
    $output = [];

    $parameter_name = $route_match->getRouteObject()->getOption('_entity_type_id');
    $entity = $route_match->getParameter($parameter_name);

    if ($entity && $entity instanceof ContentEntityInterface) {
      $tree = \Drupal::service('multiversion.entity_index.rev.tree')->getTree($entity->uuid());
      $output = [
        '#theme' => 'item_list',
        '#attributes' => ['class' => ['workspace']],
        '#attached' => ['library' => ['workspace/drupal.workspace.admin']],
        '#items' => $tree,
        '#list_type' => 'ul',
      ];
    }
    return $output;
  }

}
