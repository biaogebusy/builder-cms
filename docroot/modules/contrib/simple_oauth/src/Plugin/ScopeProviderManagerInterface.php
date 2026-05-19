<?php

namespace Drupal\simple_oauth\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Manages discovery and instantiation of Scope Provider plugins.
 */
interface ScopeProviderManagerInterface extends PluginManagerInterface {

  /**
   * Gets all scope provider plugin instances.
   *
   * @return \Drupal\simple_oauth\Plugin\ScopeProviderInterface[]
   *   Returns array of all plugin instances.
   */
  public function getInstances(): array;

}
