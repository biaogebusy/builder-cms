<?php

namespace Drupal\simple_oauth\Entity;

/**
 * Provides a trait for entities that can be locked.
 */
trait ConfigEntityLockableTrait {

  /**
   * Locked status.
   *
   * @var bool
   */
  protected $locked = FALSE;

  /**
   * {@inheritdoc}
   */
  public function isLocked() {
    return $this->locked;
  }

  /**
   * {@inheritdoc}
   */
  public function lock() {
    $this->locked = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function unlock() {
    $this->locked = FALSE;
  }

}
