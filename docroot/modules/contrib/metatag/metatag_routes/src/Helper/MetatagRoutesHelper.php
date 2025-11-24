<?php

namespace Drupal\metatag_routes\Helper;

use Drupal\Core\Routing\CurrentRouteMatch;

/**
 * Provides helper functions for generating metatag route identifiers.
 */
class MetatagRoutesHelper implements MetatagRoutesHelperInterface {

  /**
   * The current route match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * Constructs a MetatagRoutesHelper object.
   *
   * @param \Drupal\Core\Routing\CurrentRouteMatch $current_route_match
   *   The route match service.
   */
  public function __construct(CurrentRouteMatch $current_route_match) {
    $this->currentRouteMatch = $current_route_match;
  }

  /**
   * Creates a unique metatag route ID based on the route name and parameters.
   *
   * @param string $route_name
   *   The route name.
   * @param array|null $params
   *   (Optional) The route parameters.
   *
   * @return string
   *   The generated metatag route ID.
   */
  public function createMetatagRouteId($route_name, $params = NULL) {
    if ($params) {
      return $route_name . $this->getParamsHash(json_encode($params));
    }
    return $route_name;
  }

  /**
   * Gets the metatag route ID for the current route.
   *
   * @return string
   *   The metatag route ID for the current request.
   */
  public function getCurrentMetatagRouteId() {
    $route_name = $this->currentRouteMatch->getRouteName();
    $params = $this->currentRouteMatch->getRawParameters()->all();

    if ($params) {
      return $route_name . $this->getParamsHash(json_encode($params));
    }
    return $route_name;
  }

  /**
   * Generates a hash for the given parameters.
   *
   * @param mixed $params
   *   The parameters to hash.
   *
   * @return string
   *   The MD5 hash of the serialized parameters.
   */
  protected function getParamsHash($params) {
    return md5(serialize($params));
  }

}
