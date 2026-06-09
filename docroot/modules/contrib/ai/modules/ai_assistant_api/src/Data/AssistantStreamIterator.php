<?php

namespace Drupal\ai_assistant_api\Data;

use Drupal\ai\OperationType\Chat\StreamedChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIterator;

/**
 * Assistant Replay Stream message iterator.
 */
class AssistantStreamIterator extends StreamedChatMessageIterator {

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
