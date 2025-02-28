<?php

namespace Drupal\private_message\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Base class for Ajax command to insert messages into a private message thread.
 */
abstract class PrivateMessageInsertMessagesCommand implements CommandInterface {

  /**
   * The type of messages to be inserted in the page.
   *
   * Possible values:
   *   - new
   *   - previous.
   *
   * @var string
   */
  protected $insertType;

  /**
   * The HTML for the messages to be inserted in the page.
   *
   * @var string
   */
  protected $messages;

  /**
   * Construct a PrivateMessageInsertMessagesCommand object.
   *
   * @param string $insertType
   *   The type of messages to be inserted in the page. Possible values:
   *     - new
   *     - previous.
   * @param string $messages
   *   The HTML for the messages to be inserted in the page.
   * @param int $messagesCount
   *   The number of messages.
   */
  public function __construct($insertType, $messages, $messagesCount) {
    $this->insertType = $insertType;
    $this->messages = $messages;
    $this->messagesCount = $messagesCount;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'insertPrivateMessages',
      'insertType' => $this->insertType,
      'messages' => $this->messages,
      'messagesCount' => $this->messagesCount,
    ];
  }

}
