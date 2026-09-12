<?php

declare(strict_types=1);

namespace Drupal\Tests\content_lock\Functional;

use Drupal\Core\Session\AccountInterface;

/**
 * Trait for testing content lock.
 */
trait CountLocksTestTrait {

  /**
   * Counts the number of locks a user has.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   *
   * @return int
   *   The number of locks a user has.
   */
  protected function countLocks(AccountInterface $user): int {
    return (int) \Drupal::database()->select('content_lock')
      ->condition('uid', $user->id())
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}
