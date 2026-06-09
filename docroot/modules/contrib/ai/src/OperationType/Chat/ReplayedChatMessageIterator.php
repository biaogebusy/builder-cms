<?php

namespace Drupal\ai\OperationType\Chat;

/**
 * Assistant Replay Stream message iterator.
 */
class ReplayedChatMessageIterator extends StreamedChatMessageIterator {

  /**
   * The first message to append.
   */
  private string $firstMessage;

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Generator {
    $i = 0;
    foreach ($this->iterator->getIterator() as $data) {
      $text = $i ? $data->getText() : $this->firstMessage . $data->getText();
      $i++;
      yield new StreamedChatMessage(
        $data->getRole() ?? '',
        $text ?? '',
        $data->getMetadata() ?? []
      );
    }
    // If its still empty we will return the first message.
    if ($i === 0) {
      yield new StreamedChatMessage(
        'assistant',
        $this->firstMessage,
        []
      );
    }
  }

  /**
   * Set the first message.
   *
   * @param string $message
   *   The message.
   */
  public function setFirstMessage(string $message) {
    $this->firstMessage = $message;
  }

}
