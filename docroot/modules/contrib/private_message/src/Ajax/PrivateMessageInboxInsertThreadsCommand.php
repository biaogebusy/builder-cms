<?php

declare(strict_types=1);

namespace Drupal\private_message\Ajax;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Ajax\CommandInterface;

/**
 * Ajax command to insert private message inbox threads.
 */
class PrivateMessageInboxInsertThreadsCommand implements CommandInterface {

  public function __construct(
    protected readonly MarkupInterface|string $threads,
    protected readonly bool $hasMoreThreads,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => 'insertInboxOldPrivateMessageThreads',
      'threads' => (string) $this->threads,
      'hasNext' => $this->hasMoreThreads,
    ];
  }

}
