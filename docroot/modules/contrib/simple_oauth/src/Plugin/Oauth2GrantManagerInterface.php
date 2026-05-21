<?php

namespace Drupal\simple_oauth\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Manages the OAuth2 grant plugins.
 */
interface Oauth2GrantManagerInterface extends PluginManagerInterface {

  /**
   * Gets all grant type plugin instances.
   *
   * @param array|null $ids
   *   (optional) An array of plugin IDs, or NULL to load all plugins.
   *
   * @return \Drupal\simple_oauth\Plugin\Oauth2GrantInterface[]
   *   Returns array of all plugin instances.
   */
  public function getInstances(?array $ids = NULL): array;

}
