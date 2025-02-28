<?php

namespace Drupal\private_message\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Ajax command to insert a thread into the private message page.
 */
class PrivateMessageInsertThreadCommand implements CommandInterface {

  /**
   * The HTML of the thread to be inserted.
   *
   * @var string
   */
  protected $thread;

  /**
   * Constructs a PrivateMessageInsertThreadCommand object.
   *
   * @param string $thread
   *   The HTML of the thread to be inserted.
   */
  public function __construct($thread) {
    $this->thread = $thread;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'privateMessageInsertThread',
      'thread' => $this->thread,
    ];
  }

}
