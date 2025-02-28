<?php

namespace Drupal\private_message\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Ajax Command to insert private message inbox threads.
 */
class PrivateMessageInboxInsertThreadsCommand implements CommandInterface {

  /**
   * The HTML for the threads to be inserted in the page.
   *
   * @var string
   */
  protected $threads;

  /**
   * Boolean to determine if there are more threads to come.
   *
   * @var bool
   */
  protected $hasNext;

  /**
   * Constructs a PrivateMessageInboxInsertThreadsCommand object.
   *
   * @param string $threads
   *   The HTML for the threads to be inserted in the page.
   * @param bool $has_next
   *   A boolean to know if there are more threads after.
   */
  public function __construct($threads, $has_next) {
    $this->threads = $threads;
    $this->hasNext = $has_next;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'insertInboxOldPrivateMessageThreads',
      'threads' => $this->threads,
      'hasNext' => $this->hasNext,
    ];
  }

}
