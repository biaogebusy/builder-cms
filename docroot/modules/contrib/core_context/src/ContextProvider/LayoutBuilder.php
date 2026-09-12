<?php

namespace Drupal\core_context\ContextProvider;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\Routing\Route;

/**
 * Exposes contexts available at Layout Builder routes.
 */
final class LayoutBuilder extends RouteAwareContextProviderBase {

  /**
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  private $entityDisplayRepository;

  /**
   * The 'core_context.canonical_entity' service.
   *
   * @var \Drupal\core_context\ContextProvider\CanonicalEntity
   */
  private $canonical;

  /**
   * LayoutBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository service.
   * @param \Drupal\core_context\ContextProvider\CanonicalEntity $canonical
   *   The 'core_context.canonical_entity' service.
   * @param mixed ...$arguments
   *   Additional arguments to pass to the parent constructor.
   */
  public function __construct(EntityDisplayRepositoryInterface $entity_display_repository, CanonicalEntity $canonical, ...$arguments) {
    $this->entityDisplayRepository = $entity_display_repository;
    $this->canonical = $canonical;
    parent::__construct(...$arguments);
  }

  /**
   * Extracts the entity type ID from the current route.
   *
   * We expect to be on the Layout Builder UI, which we can identify using the
   * _entity_form default, which will carry a value of
   * ENTITY_TYPE_ID.layout_builder.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The current route object.
   *
   * @see \Drupal\layout_builder\Routing\LayoutBuilderRoutesTrait
   *
   * @return string
   *   The entity type and view mode, separated by a period. If we are not on
   *   a canonical entity route, an empty string is returned.
   */
  private function getEntityTypeFromRoute(Route $route) {
    $match = [];
    preg_match('/([a-zA-Z0-9_]+)\.layout_builder$/', (string) $route->getDefault('_entity_form'), $match);
    return $match ? $match[1] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function appliesTo(Route $route) : bool {
    $entity_type_id = $this->getEntityTypeFromRoute($route);
    return isset($entity_type_id);
  }

  /**
   * {@inheritdoc}
   */
  protected function getContextsFromRoute(Route $route) : array {
    $entity_type_id = $this->getEntityTypeFromRoute($route);

    // If this is an entity view display, we are editing a default layout.
    // Otherwise, we are editing an entity-specific override and can delegate to
    // the canonical entity provider (albeit by prying its implementation of
    // this method open by reflection).
    if ($entity_type_id === 'entity_view_display') {
      $entity_type_id = $this->routeMatch->getParameter('entity_type_id');
      // If there is no bundle_key parameter, the entity type whose layout we
      // editing does not support bundles.
      $bundle_key = $this->routeMatch->getParameter('bundle_key');
      $bundle = isset($bundle_key) ? $this->routeMatch->getParameter($bundle_key) : $entity_type_id;
      // With certain entity types, such as media items, the bundle is a fully
      // loaded config entity. In such a case, we only need its ID.
      if ($bundle instanceof EntityInterface) {
        $bundle = $bundle->id();
      }
      $view_mode = $this->routeMatch->getParameter('view_mode_name');

      $display = $this->entityDisplayRepository->getViewDisplay($entity_type_id, $bundle, $view_mode);
      return $this->getContextsFromEntity($display);
    }
    else {
      // For now, we can (more or less) assume that 'full' is the canonical
      // view mode.
      // @see \Drupal\layout_builder\Form\LayoutBuilderEntityViewDisplayForm::isCanonicalMode()
      $route->setDefault('_core_context_entity', "$entity_type_id.full");

      $reflector = new \ReflectionObject($this->canonical);
      $method = $reflector->getMethod(__FUNCTION__);
      $method->setAccessible(TRUE);
      return $method->invoke($this->canonical, $route);
    }
  }

}
