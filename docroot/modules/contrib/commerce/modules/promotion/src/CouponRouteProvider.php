<?php

namespace Drupal\commerce_promotion;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for the Coupon entity.
 */
class CouponRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);

    foreach (['enable', 'disable'] as $operation) {
      if ($form_route = $this->getCouponFormRoute($entity_type, $operation)) {
        $collection->add('entity.commerce_promotion_coupon.' . $operation . '_form', $form_route);
      }
    }

    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAddFormRoute(EntityTypeInterface $entity_type) {
    $route = parent::getAddFormRoute($entity_type);
    $route->setOption('parameters', [
      'commerce_promotion' => [
        'type' => 'entity:commerce_promotion',
      ],
    ]);
    // Coupons can be created if the parent promotion can be updated.
    $requirements = $route->getRequirements();
    unset($requirements['_entity_create_access']);
    $requirements['_entity_access'] = 'commerce_promotion.update';
    $route->setRequirements($requirements);

    return $route;
  }

  /**
   * Gets a coupon form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $operation
   *   The 'operation' (e.g 'disable', 'enable').
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getCouponFormRoute(EntityTypeInterface $entity_type, $operation) {
    if ($entity_type->hasLinkTemplate($operation . '-form')) {
      $route = new Route($entity_type->getLinkTemplate($operation . '-form'));
      $route
        ->addDefaults([
          '_entity_form' => "commerce_promotion_coupon.$operation",
          '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::title',
        ])
        ->setRequirement('_entity_access', 'commerce_promotion_coupon.update')
        ->setOption('parameters', [
          'commerce_promotion' => [
            'type' => 'entity:commerce_promotion',
          ],
          'commerce_promotion_coupon' => [
            'type' => 'entity:commerce_promotion_coupon',
          ],
        ])
        ->setRequirement('commerce_promotion', '\d+')
        ->setRequirement('commerce_promotion_coupon', '\d+')
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

}
