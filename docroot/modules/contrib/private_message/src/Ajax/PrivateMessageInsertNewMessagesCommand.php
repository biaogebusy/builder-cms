<?php

namespace Drupal\private_message\Ajax;

/**
 * Class to insert new private messages into a private message thread.
 */
class PrivateMessageInsertNewMessagesCommand extends PrivateMessageInsertMessagesCommand {

  /**
   * Constructs a PrivateMessageInsertNewMessagesCommand object.
   *
   * @param string $messages
   *   The HTML for the messages to be inserted in the page.
   * @param int $messagesCount
   *   The number of messages.
   */
  public function __construct($messages, $messagesCount) {
    parent::__construct('new', $messages, $messagesCount);
  }

}
