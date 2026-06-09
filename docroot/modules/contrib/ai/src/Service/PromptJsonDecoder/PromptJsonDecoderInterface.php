<?php

namespace Drupal\ai\Service\PromptJsonDecoder;

use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;

/**
 * Decode JSON from a chat message.
 */
interface PromptJsonDecoderInterface {

  /**
   * Decode the JSON payload into an array.
   *
   * Note that since a streaming message gets consumed in chunks, we need to
   * test the first few chunks to see if we can decode the message. If we can't
   * decode the message, we return the message back as a pseudo streaming
   * message.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatMessage|\Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface $payload
   *   The JSON payload to decode.
   * @param int $chunks_to_test
   *   The number of chunks of a streaming message to test before giving up.
   *
   * @return array|\Drupal\ai\OperationType\Chat\ChatMessage|\Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface
   *   The decoded JSON payload or the message back.
   */
  public function decode(ChatMessage|StreamedChatMessageIteratorInterface $payload, $chunks_to_test = 10): array|ChatMessage|StreamedChatMessageIteratorInterface;

}
