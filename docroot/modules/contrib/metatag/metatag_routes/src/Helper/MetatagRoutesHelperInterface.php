<?php

namespace Drupal\metatag_routes\Helper;

/**
 * Provides an interface for handling Metatag route identification.
 *
 * Defines methods to generate unique Metatag route IDs based on
 * route names and parameters.
 *
 * @package Drupal\metatag_routes\Helper
 */
interface MetatagRoutesHelperInterface {

  /**
   * Create metatag route ID from given route name and parameters.
   *
   * @param string $route_name
   *   Route name.
   * @param array $params
   *   Raw route parameters.
   *
   * @return string
   *   Created metatag route id.
   */
  public function createMetatagRouteId($route_name, $params = NULL);

  /**
   * Return current metatag route ID.
   *
   * @return string
   *   Created metatag route id.
   */
  public function getCurrentMetatagRouteId();

}
