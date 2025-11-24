<?php

namespace Drupal\commerce_store;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for the Store entity.
 */
class StoreRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);

    foreach (['enable', 'disable'] as $operation) {
      if ($form_route = $this->getStoreFormRoute($entity_type, $operation)) {
        $collection->add('entity.commerce_store.' . $operation . '_form', $form_route);
      }
    }

    return $collection;
  }

  /**
   * Gets a store form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $operation
   *   The 'operation' (e.g 'disable', 'enable').
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getStoreFormRoute(EntityTypeInterface $entity_type, $operation) {
    if ($entity_type->hasLinkTemplate($operation . '-form')) {
      $route = new Route($entity_type->getLinkTemplate($operation . '-form'));
      $route
        ->addDefaults([
          '_entity_form' => "commerce_store.$operation",
          '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::title',
        ])
        ->setRequirement('_entity_access', 'commerce_store.update')
        ->setOption('parameters', [
          'commerce_store' => [
            'type' => 'entity:commerce_store',
          ],
        ])
        ->setRequirement('commerce_store', '\d+')
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

}
