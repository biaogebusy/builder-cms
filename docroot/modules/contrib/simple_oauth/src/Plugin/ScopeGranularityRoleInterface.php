<?php

namespace Drupal\simple_oauth\Plugin;

/**
 * Defines an interface for the Role Scope Granularity plugin.
 */
interface ScopeGranularityRoleInterface {

  /**
   * Returns a list of roles.
   *
   * @param bool $exclude_locked_roles
   *   If TRUE, locked roles (anonymous/authenticated) are not returned.
   *
   * @return string[]
   *   List of role IDs.
   */
  public function getRoles(bool $exclude_locked_roles = FALSE): array;

}
