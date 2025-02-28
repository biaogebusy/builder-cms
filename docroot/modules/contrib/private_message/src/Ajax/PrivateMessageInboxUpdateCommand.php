<?php

namespace Drupal\private_message\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Ajax command to update the private message inbox block.
 */
class PrivateMessageInboxUpdateCommand implements CommandInterface {

  /**
   * The thread IDs, in the order that they should appear upon update.
   *
   * @var array
   */
  protected $threadIds;

  /**
   * HTML for any threads that don't currently exist in the inbox.
   *
   * @var array
   */
  protected $newThreads;

  /**
   * Constructs a PrivateMessageInboxUpdateCommand object.
   *
   * @param array $threadIds
   *   The thread IDs, in the order that they should appear when the inbox is
   *   updated.
   * @param array $newThreads
   *   The HTML for the messages to be inserted in the page.
   */
  public function __construct(array $threadIds, array $newThreads) {
    $this->threadIds = $threadIds;
    $this->newThreads = $newThreads;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'privateMessageInboxUpdate',
      'threadIds' => $this->threadIds,
      'newThreads' => $this->newThreads,
    ];
  }

}
