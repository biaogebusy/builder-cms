<?php

namespace Drupal\simple_oauth\Entity;

/**
 * Provides an interface for entities that can be locked.
 */
interface ConfigEntityLockableInterface {

  /**
   * Checks if the entity is locked against changes.
   *
   * @return bool
   *   Return TRUE if the entity is locked, otherwise FALSE.
   */
  public function isLocked();

  /**
   * Locks the entity.
   */
  public function lock();

  /**
   * Unlocks the entity.
   */
  public function unlock();

}
