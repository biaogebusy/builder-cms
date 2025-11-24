<?php

declare(strict_types=1);

namespace Drupal\private_message\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Ajax command to insert a thread into the private message page.
 */
class PrivateMessageInsertThreadCommand implements CommandInterface {

  public function __construct(protected readonly string $threadHtml) {}

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => 'privateMessageInsertThread',
      'thread' => $this->threadHtml,
    ];
  }

}
