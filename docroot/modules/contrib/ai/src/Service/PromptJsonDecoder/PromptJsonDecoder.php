<?php

namespace Drupal\ai\Service\PromptJsonDecoder;

use Drupal\Component\Serialization\Json;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ReplayedChatMessageIterator;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;

/**
 * Decode JSON from a chat message.
 */
class PromptJsonDecoder implements PromptJsonDecoderInterface {

  /**
   * Starting combinations of characters that could be JSON.
   *
   * @var string[]
   */
  protected array $jsonStart = [
    '{"',
    '{\"',
    '[{',
    '```',
    "{\n",
  ];

  /**
   * {@inheritdoc}
   */
  public function decode(ChatMessage|StreamedChatMessageIteratorInterface $payload, $chunks_to_test = 10): array|ChatMessage|StreamedChatMessageIteratorInterface {
    // If its a normal ChatOutput, we can decode it directly.
    if ($payload instanceof ChatMessage) {
      // Return the data or if its null, the same payload.
      $data = $this->decodePayload($payload->getText());
      return $data ?? $payload;
    }
    else {
      // If its streaming, we need another test first.
      return $this->decodeStreamingMessage($payload, $chunks_to_test);
    }
  }

  /**
   * Stream the message and test the first few chunks to see if we decode it.
   *
   * @param \Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface $payload
   *   The streaming message to decode.
   * @param int $chunks_to_test
   *   The number of chunks of a streaming message to test before giving up.
   *
   * @return array|\Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface
   *   The decoded JSON payload or the message back.
   */
  public function decodeStreamingMessage(StreamedChatMessageIteratorInterface $payload, int $chunks_to_test): array|StreamedChatMessageIteratorInterface {
    // First we test if it could be json.
    $i = 0;
    // We store what we have tested.
    $full = "";
    $could_be_json = FALSE;
    foreach ($payload as $value) {
      $full .= $value->getText();
      if ($i >= $chunks_to_test) {
        break;
      }
      if ($value->getText()) {
        // Test against the different start combinations.
        foreach ($this->jsonStart as $start) {
          if (strpos($full, $start) !== FALSE) {
            $could_be_json = TRUE;
            // Stop because its a candidate.
            break 2;
          }
        }
      }
      $i++;
    }

    // If its a candidate, we decode it.
    if ($could_be_json) {
      // Lets run the whole thing.
      foreach ($payload as $value) {
        $full .= $value->getText();
      }
      $data = $this->decodePayload($full);
      // If we could decode it, we return the JSON data.
      if ($data) {
        return $data;
      }
    }

    // Since its not a valid JSON or we couldn't decode it, we return a stream.
    $return_stream = new ReplayedChatMessageIterator($payload);
    $return_stream->setFirstMessage($full);
    return $return_stream;
  }

  /**
   * Decode the JSON payload into an array.
   *
   * @param string $payload
   *   The JSON payload to decode.
   *
   * @return array|null
   *   The decoded JSON payload or NULL if the payload is not valid JSON.
   */
  public function decodePayload(string $payload): ?array {
    // Regex to find most patterns.
    $pattern = '/\{(?:[^{}]|(?R))*\}|\[(?:[^\[\]]|(?R))*\]/';
    preg_match_all($pattern, $payload, $matches);
    foreach ($matches[0] as $possible_json) {
      $data = Json::decode(is_array($possible_json) ? $possible_json[0] : $possible_json);
      if (json_last_error() === JSON_ERROR_NONE) {
        return $data;
      }
    }
    // If its specifically inside ```json and ``` we try to decode it.
    if (preg_match('/```json(.*)```/s', $payload, $matches)) {
      // Trim whitespace, tabs and newline from start and end.
      $possible_json = trim($matches[1], "\t\n\r\0\x0B");
      $data = Json::decode($possible_json);
      if (json_last_error() === JSON_ERROR_NONE) {
        return $data;
      }
    }

    return NULL;
  }

}
