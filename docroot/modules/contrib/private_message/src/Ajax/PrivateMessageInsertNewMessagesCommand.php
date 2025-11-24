<?php

declare(strict_types=1);

namespace Drupal\private_message\Ajax;

/**
 * Class to insert new private messages into a private message thread.
 */
class PrivateMessageInsertNewMessagesCommand extends PrivateMessageInsertMessagesCommandBase {

  /**
   * Constructs a new command instance.
   *
   * @param string $messages
   *   The HTML for the messages to be inserted in the page.
   * @param int $messagesCount
   *   The number of messages.
   */
  public function __construct(string $messages, int $messagesCount) {
    parent::__construct('new', $messages, $messagesCount);
  }

}
