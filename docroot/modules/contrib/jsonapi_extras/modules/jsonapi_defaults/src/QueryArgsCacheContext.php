<?php

namespace Drupal\jsonapi_defaults;

use Drupal\Core\Cache\Context\QueryArgsCacheContext as CoreQueryArgsCacheContext;
use Drupal\jsonapi_extras\Entity\JsonapiResourceConfig;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Override core QueryArgsCacheContext service.
 *
 * To ensure that calls to JSON API resources with default includes are
 * cacheable, it is necessary to ignore all default includes arguments.
 * This is because they are set in the
 * 'Drupal\jsonapi_defaults\Controller\EntityResource::getIncludes' to the
 * request dynamically before the cache is set. On the other hand, if the cache
 * is stored in the database, 'getIncludes' is executed after Drupal attempts to
 * retrieve the cache. This results in a situation where we always receive an
 * uncached response due to a mismatch with include arguments.
 */
class QueryArgsCacheContext extends CoreQueryArgsCacheContext {

  /**
   * The jsonapi defaults service.
   *
   * @var \Drupal\jsonapi_defaults\JsonapiDefaultsInterface
   */
  protected JsonapiDefaultsInterface $jsonapiDefaults;

  /**
   * Constructs a new RequestStackCacheContextBase class.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\jsonapi_defaults\JsonapiDefaultsInterface $jsonapi_defaults
   *   The jsonapi default service.
   */
  public function __construct(RequestStack $request_stack, JsonapiDefaultsInterface $jsonapi_defaults) {
    parent::__construct($request_stack);
    $this->jsonapiDefaults = $jsonapi_defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function getContext($query_arg = NULL) {
    // Ignore context if request contains default includes arguments.
    if ($query_arg == 'include' && $this->hasOnlyDefaultIncludes()) {
      return '';
    }

    return parent::getContext($query_arg);
  }

  /**
   * Check if the request contains only default includes.
   *
   * @return bool
   *   TRUE if the request contains only default includes, FALSE otherwise..
   */
  protected function hasOnlyDefaultIncludes(): bool {
    try {
      $resourceConfig = $this->jsonapiDefaults->getResourceConfigFromRequest(
        $this->requestStack->getCurrentRequest()
      );
    }
    catch (\LengthException $e) {
      return FALSE;
    }

    if (!$resourceConfig instanceof JsonapiResourceConfig) {
      return FALSE;
    }

    $default_includes = $resourceConfig->getThirdPartySetting(
      'jsonapi_defaults',
      'default_include',
      []
    );

    $current_request = $this->requestStack->getCurrentRequest();
    $current_includes = $current_request->query->get('include');

    if ($current_includes) {
      $current_includes = explode(',', (string) $current_includes);
      return empty(array_diff($current_includes, $default_includes));
    }
    else {
      return !empty($default_includes);
    }
  }

}
