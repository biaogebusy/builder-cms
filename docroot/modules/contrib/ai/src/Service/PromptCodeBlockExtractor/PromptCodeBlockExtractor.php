<?php

namespace Drupal\ai\Service\PromptCodeBlockExtractor;

use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ReplayedChatMessageIterator;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;

/**
 * Extract Code Block from a chat message.
 */
class PromptCodeBlockExtractor implements PromptCodeBlockExtractorInterface {

  /**
   * The possible code block types.
   *
   * The two different types, between and regex exists. Between is used when
   * parsing line by line and regex is used when parsing the whole message.
   *
   * @var array
   */
  public $codeBlockTypes = [
    'html' => [
      'label' => 'HTML',
      'rules' => [
        [
          'type' => 'regex',
          'rule' => '/```html\n(.*)```/s',
        ],
        [
          'type' => 'regex',
          'rule' => '/<html>(.*)<\/html>/s',
        ],
        [
          // Find on the first and the last tag blocks of any type.
          'type' => 'regex',
          'rule' => '/<.*?>(.*)<\/.*?>/s',
        ],
      ],
    ],
    'twig' => [
      'label' => 'Twig',
      'rules' => [
        [
          'type' => 'regex',
          'rule' => '/```twig\n(.*)```/s',
        ],
        [
          'type' => 'regex',
          'rule' => '/<html>(.*)<\/html>/s',
        ],
        [
          // Find on the first and the last tag blocks of any type.
          'type' => 'regex',
          'rule' => '/<.*?>(.*)<\/.*?>/s',
        ],
      ],
    ],
    'yaml' => [
      'label' => 'YAML',
      'rules' => [
        [
          'type' => 'regex',
          'rule' => '/```yaml\n(.*)```/s',
        ],
      ],
    ],
    'json' => [
      'label' => 'JSON',
      'rules' => [
        [
          'type' => 'regex',
          'rule' => '/```json\n(.*)```/s',
        ],
      ],
    ],
    'css' => [
      'label' => 'CSS',
      'rules' => [
        [
          'type' => 'regex',
          'rule' => '/```css\n(.*)```/s',
        ],
      ],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function extract(string|ChatMessage|StreamedChatMessageIteratorInterface $payload, $code_block_type = 'html'): string|ChatMessage|StreamedChatMessageIteratorInterface {
    // If its not in the list.
    if (!isset($this->codeBlockTypes[$code_block_type])) {
      return $payload;
    }
    // If its a normal ChatOutput, we can extract it directly.
    if ($payload instanceof ChatMessage) {
      // Return the data or if its null, the same payload.
      $data = $this->extractPayload($payload->getText(), $code_block_type);
      return $data ?? $payload;
    }
    elseif (gettype($payload) === 'string') {
      // If its a string, we can extract it directly.
      $data = $this->extractPayload($payload, $code_block_type);
      return $data ?? $payload;
    }
    else {
      // If its streaming, we need another test first.
      return $this->extractStreamingMessage($payload, $code_block_type);
    }
  }

  /**
   * Stream the message and test the first few chunks to see if we extract it.
   *
   * @param \Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface $payload
   *   The streaming message to extract.
   * @param string $code_block_type
   *   The type of Code Block to extract.
   *
   * @return string|\Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface
   *   The extracted code block payload or the message back.
   */
  public function extractStreamingMessage(string|StreamedChatMessageIteratorInterface $payload, $code_block_type): string|StreamedChatMessageIteratorInterface {
    $full = '';
    foreach ($payload as $value) {
      $full .= $value->getText();
    }

    // Check if we can extract it.
    $data = $this->extractPayload($full, $code_block_type);
    if ($data) {
      return $data;
    }

    // Since it did not find anything, we return a stream.
    $return_stream = new ReplayedChatMessageIterator($payload);
    $return_stream->setFirstMessage($full);
    return $return_stream;
  }

  /**
   * Extract the code block payload into an array.
   *
   * @param string $payload
   *   The code block payload to extract.
   * @param string $code_block_type
   *   The type of code block to extract.
   *
   * @return string|null
   *   The extract code block payload or NULL if its not valid code block.
   */
  public function extractPayload(string $payload, $code_block_type): ?string {
    // Check all the rules.
    foreach ($this->codeBlockTypes[$code_block_type]['rules'] as $rule) {
      // Iterate over the rules.
      if ($rule['type'] === 'between') {
        $total = "";
        foreach (explode("\n", $payload) as $line) {
          if (trim($line) === $rule['rule'][0]) {
            if ($rule['add_rule']) {
              $total .= $line;
            }
            continue;
          }
          if (trim($line) === $rule['rule'][1]) {
            if ($rule['add_rule']) {
              $total .= $line;
            }
            return $total;
          }
          $total .= $line . "\n";
        }
      }
      elseif ($rule['type'] === 'regex') {
        // Check if we can find it.
        if (preg_match($rule['rule'], $payload, $matches)) {
          if (isset($matches[1])) {
            return $matches[1];
          }
        }
      }
    }

    return NULL;
  }

}
