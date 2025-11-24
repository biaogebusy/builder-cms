<?php

declare(strict_types=1);

namespace Drupal\private_message\Ajax;

/**
 * Class to insert older private messages into a private message thread.
 */
class PrivateMessageInsertPreviousMessagesCommand extends PrivateMessageInsertMessagesCommandBase {

  /**
   * Constructs a new command instance.
   *
   * @param string $messages
   *   The HTML for the messages to be inserted in the page.
   * @param int $messagesCount
   *   The number of messages.
   * @param bool $hasNext
   *   A boolean to know if there are more messages after.
   */
  public function __construct(
    string $messages,
    int $messagesCount,
    protected readonly bool $hasNext,
  ) {
    parent::__construct('previous', $messages, $messagesCount);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $return = parent::render();
    $return['hasNext'] = $this->hasNext;
    return $return;
  }

}
