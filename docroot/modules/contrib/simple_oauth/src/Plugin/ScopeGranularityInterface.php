<?php

namespace Drupal\simple_oauth\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines an interface for Scope Granularity plugins.
 */
interface ScopeGranularityInterface extends ConfigurableInterface, PluginFormInterface, PluginInspectionInterface {

  /**
   * Validates the plugin configuration.
   *
   * @param array $configuration
   *   The configuration to be validated.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function validateConfiguration(array $configuration): void;

  /**
   * Checks access to a permission.
   *
   * @param string $permission
   *   The name of the permission to check.
   *
   * @return bool
   *   TRUE if the access to the permission is granted; FALSE otherwise.
   */
  public function hasPermission(string $permission): bool;

  /**
   * Returns a list of permissions assigned to the scope.
   *
   * @return array
   *   The permissions assigned to the scope.
   */
  public function getPermissions(): array;

}
