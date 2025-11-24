<?php

declare(strict_types=1);

namespace Drupal\private_message\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Ajax command to update the private message inbox block.
 */
class PrivateMessageInboxUpdateCommand implements CommandInterface {

  /**
   * Constructs a new command instance.
   *
   * @param array $threadIds
   *   The thread IDs, in the order that they should appear when the inbox is
   *   updated.
   * @param array $newThreads
   *   The HTML for the messages to be inserted in the page.
   */
  public function __construct(
    protected readonly array $threadIds,
    protected readonly array $newThreads,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => 'privateMessageInboxUpdate',
      'threadIds' => $this->threadIds,
      'newThreads' => $this->newThreads,
    ];
  }

}
