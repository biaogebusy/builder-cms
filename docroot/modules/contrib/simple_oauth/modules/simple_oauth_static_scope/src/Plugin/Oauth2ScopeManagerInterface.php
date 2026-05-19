<?php

namespace Drupal\simple_oauth_static_scope\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Manages discovery and instantiation of OAuth2 scope plugins.
 */
interface Oauth2ScopeManagerInterface extends PluginManagerInterface {

  /**
   * Gets all scope plugin instances.
   *
   * @param array|null $ids
   *   (optional) An array of plugin IDs, or NULL to load all plugins.
   *
   * @return array
   *   Returns array of all plugin instances.
   */
  public function getInstances(?array $ids = NULL): array;

  /**
   * Get the children by parent scope id.
   *
   * @param string $parent_id
   *   The parent scope id.
   *
   * @return array
   *   Returns array of plugin instances.
   */
  public function getChildrenInstances(string $parent_id): array;

}
