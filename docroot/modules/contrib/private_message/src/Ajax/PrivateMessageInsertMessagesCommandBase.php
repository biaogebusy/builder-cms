<?php

declare(strict_types=1);

namespace Drupal\private_message\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Base class for Ajax command to insert messages into a private message thread.
 */
abstract class PrivateMessageInsertMessagesCommandBase implements CommandInterface {

  /**
   * Constructs a new command instance.
   *
   * @param string $insertType
   *   The type of messages to be inserted in the page: 'new', 'previous'.
   * @param string $messages
   *   The HTML for the messages to be inserted in the page.
   * @param int $messagesCount
   *   The number of messages.
   */
  public function __construct(
    protected readonly string $insertType,
    protected readonly string $messages,
    protected readonly int $messagesCount,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => 'insertPrivateMessages',
      'insertType' => $this->insertType,
      'messages' => $this->messages,
      'messagesCount' => $this->messagesCount,
    ];
  }

}
