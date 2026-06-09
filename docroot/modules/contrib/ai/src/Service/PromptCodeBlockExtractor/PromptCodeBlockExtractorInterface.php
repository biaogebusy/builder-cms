<?php

namespace Drupal\ai\Service\PromptCodeBlockExtractor;

use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;

/**
 * Extract code block from a chat message.
 */
interface PromptCodeBlockExtractorInterface {

  /**
   * Extract the code block payload into an array.
   *
   * Note that this will consume the whole streaming message if its streamed.
   * This means that its just streamed in the abstraction sense if you invoke
   * this method, for all practical purposes its not streamed anymore.
   *
   * @param string|\Drupal\ai\OperationType\Chat\ChatMessage|\Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface $payload
   *   The code block payload or string to extract.
   * @param string $code_block_type
   *   The type of code block to extract.
   *
   * @return string|\Drupal\ai\OperationType\Chat\ChatMessage|\Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface
   *   The code block as a string or the message back.
   */
  public function extract(string|ChatMessage|StreamedChatMessageIteratorInterface $payload, $code_block_type = 'html'): string|ChatMessage|StreamedChatMessageIteratorInterface;

}
