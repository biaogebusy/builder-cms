<?php

namespace Drupal\core_context\ContextProvider;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Symfony\Component\Routing\Route;

/**
 * Exposes contexts available at a canonical entity route.
 */
final class CanonicalEntity extends RouteAwareContextProviderBase {

  /**
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  private $entityDisplayRepository;

  /**
   * CanonicalEntity constructor.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository service.
   * @param mixed ...$arguments
   *   Additional arguments to pass to the parent constructor.
   */
  public function __construct(EntityDisplayRepositoryInterface $entity_display_repository, ...$arguments) {
    $this->entityDisplayRepository = $entity_display_repository;
    parent::__construct(...$arguments);
  }

  /**
   * Extracts the entity type and view mode from the current route.
   *
   * We expect to be on a canonical entity route. That means we expect the route
   * to have an _entity_view default which carries the entity type being viewed
   * and the view mode being used, separated by a period. Certain routes, like
   * entity.node.canonical, don't have this default. So our route subscriber
   * polyfills it by adding a _core_context_entity default containing the
   * required information.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The current route object.
   *
   * @see \Drupal\core_context\Routing\RouteSubscriber::alterRoutes()
   *
   * @return string
   *   The entity type and view mode, separated by a period. If we are not on
   *   a canonical entity route, an empty string is returned.
   */
  private function getEntityTypeAndViewModeFromRoute(Route $route) : string {
    return strval($route->getDefault('_core_context_entity') ?: $route->getDefault('_entity_view'));
  }

  /**
   * {@inheritdoc}
   */
  protected function appliesTo(Route $route) : bool {
    return strpos($this->getEntityTypeAndViewModeFromRoute($route), '.') > 0;
  }

  /**
   * {@inheritdoc}
   */
  protected function getContextsFromRoute(Route $route) : array {
    list ($entity_type_id, $view_mode) = explode('.', $this->getEntityTypeAndViewModeFromRoute($route));

    $entity = $this->routeMatch->getParameter($entity_type_id);
    $contexts = $this->getContextsFromEntity($entity);

    // Only fieldable entities can have entity displays associated with them.
    // We need to check this here in order to avoid raising an exception if
    // the entity view display does not already exist.
    // @see \Drupal\Core\Entity\EntityDisplayBase::__construct()
    if ($entity instanceof FieldableEntityInterface) {
      $display = $this->entityDisplayRepository->getViewDisplay($entity_type_id, $entity->bundle(), $view_mode);
      $contexts = array_merge($contexts, $this->getContextsFromEntity($display));
    }
    return $contexts;
  }

}
