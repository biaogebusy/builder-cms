<?php

namespace Drupal\core_context\ContextProvider;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\core_context\CacheableContextTrait;
use Symfony\Component\Routing\Route;

/**
 * Provides a base class for context providers which use the current route.
 */
abstract class RouteAwareContextProviderBase extends ContextProviderBase {

  use CacheableContextTrait;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * RouteAwareContextProviderBase constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param mixed ...$arguments
   *   Additional arguments to pass to the parent constructor.
   */
  public function __construct(RouteMatchInterface $route_match, ...$arguments) {
    $this->routeMatch = $route_match;
    parent::__construct(...$arguments);
  }

  /**
   * Determines if this provider can extract contexts from the current route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route object.
   *
   * @return bool
   *   TRUE if this provider can extract contexts from the route, otherwise
   *   FALSE.
   */
  abstract protected function appliesTo(Route $route) : bool;

  /**
   * Extracts contexts from the current route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route object.
   *
   * @return \Drupal\Component\Plugin\Context\ContextInterface[]
   *   The contexts extracted from the route, keyed by name. Any contexts which
   *   can accept cache metadata will get the 'route' cache context applied.
   */
  abstract protected function getContextsFromRoute(Route $route) : array;

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $route = $this->routeMatch->getRouteObject();
    if (empty($route) || $this->appliesTo($route) === FALSE) {
      return [];
    }

    $contexts = $this->getContextsFromRoute($route);
    if ($unqualified_context_ids) {
      $contexts = array_intersect_key($contexts, array_flip($unqualified_context_ids));
    }

    $cache_metadata = new CacheableMetadata();
    $cache_metadata->addCacheContexts(['route']);

    return $this->applyCaching($contexts, $cache_metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    return $this->getRuntimeContexts([]);
  }

}
