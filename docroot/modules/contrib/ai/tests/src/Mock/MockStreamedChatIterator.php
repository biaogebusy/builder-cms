<?php

namespace Drupal\Tests\ai\Mock;

use Drupal\ai\OperationType\Chat\StreamedChatMessageIterator;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;

/**
 * Mock chat iterator for testing.
 */
class MockStreamedChatIterator extends StreamedChatMessageIterator implements StreamedChatMessageIteratorInterface {

  /**
   * Get the iterator.
   *
   * @return \Generator
   *   The iterator.
   */
  public function doIterate(): \Generator {
    foreach ($this->iterator as $data) {
      yield $this->createStreamedChatMessage(
        'assistant',
        $data,
        [],
      );
    }
  }

}
