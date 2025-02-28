<?php

namespace Drupal\state_machine\Guard;

/**
 * Defines the interface for guard factories.
 */
interface GuardFactoryInterface {

  /**
   * Gets the instantiated guards for the given group ID.
   *
   * @param string $group_id
   *   The group ID.
   *
   * @return \Drupal\state_machine\Guard\GuardInterface[]
   *   The instantiated guards.
   */
  public function get($group_id);

}
