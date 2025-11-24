<?php

declare(strict_types=1);

namespace Drupal\Tests\private_message\Traits;

use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * Reusable inbox block code.
 */
trait InboxBlockTestTrait {

  /**
   * Get threads.
   *
   * @return array
   *   Threads.
   */
  protected function getThreads(): array {
    return $this->getSession()
      ->getPage()
      ->findAll('css', '.private-message-thread-inbox');
  }

  /**
   * Counts threads.
   *
   * @return int
   *   Number of threads.
   */
  protected function countThreads(): int {
    return count($this->getThreads());
  }

  /**
   * Click on thread.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageThreadInterface $thread
   *   Thread entity.
   */
  protected function clickThread(PrivateMessageThreadInterface $thread): void {
    $this->click('.private-message-inbox-thread-link[data-thread-id="' . $thread->id() . '"]');
  }

}
