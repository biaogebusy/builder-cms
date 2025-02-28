<?php

namespace Drupal\private_message\Ajax;

/**
 * Class to insert older private messages into a private message thread.
 */
class PrivateMessageInsertPreviousMessagesCommand extends PrivateMessageInsertMessagesCommand {

  /**
   * Boolean to determine if there are more threads to come.
   *
   * @var bool
   */
  protected $hasNext;

  /**
   * Constructs a PrivateMessageInsertPreviousMessagesCommand object.
   *
   * @param string $messages
   *   The HTML for the messages to be inserted in the page.
   * @param int $messagesCount
   *   The number of messages.
   * @param bool $has_next
   *   A boolean to know if there are more messages after.
   */
  public function __construct($messages, $messagesCount, $has_next) {
    parent::__construct('previous', $messages, $messagesCount);
    $this->hasNext = $has_next;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $return = parent::render();
    $return['hasNext'] = $this->hasNext;
    return $return;
  }

}
