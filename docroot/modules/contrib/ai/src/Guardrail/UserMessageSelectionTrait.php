<?php

declare(strict_types=1);

namespace Drupal\ai\Guardrail;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

/**
 * Helper for selecting which user messages a guardrail should evaluate.
 *
 * Guardrail plugins historically called end($messages) to grab a single
 * message to scan, with no role check. That breaks for two situations:
 *
 * - Conversation history that was loaded from storage (or imported, replayed,
 *   or scanned under different rules) so earlier messages were never seen
 *   by the current guardrail configuration.
 * - Tool-use turns where the last message is a role=tool result fed back for
 *   a follow-up generation. The actual user prompt is silently swapped out
 *   of the haystack.
 *
 * This trait centralizes the selection so each plugin has one consistent
 * way to pick the messages it should scan.
 */
trait UserMessageSelectionTrait {

  /**
   * Builds the form element to configure user message scanning behavior.
   *
   * @param string $description
   *   The checkbox description text.
   * @param bool $default_value
   *   The default checkbox value.
   *
   * @return array<string, mixed>
   *   A Drupal form API element definition.
   */
  protected function buildScanAllUserMessagesElement(string $description, bool $default_value): array {
    return [
      '#type' => 'checkbox',
      '#title' => $this->t('Scan all user messages in the conversation'),
      '#description' => $description,
      '#default_value' => $default_value,
    ];
  }

  /**
   * Returns the user messages that should be scanned for a chat input.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatInput $input
   *   The chat input to inspect.
   * @param bool $scan_all
   *   When TRUE, every role=user ChatMessage in the conversation is returned
   *   in original order. When FALSE, only the most recent role=user
   *   ChatMessage is returned (or an empty array if none exist).
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage[]
   *   Zero or more chat messages with role=user.
   */
  protected function selectUserMessages(ChatInput $input, bool $scan_all): array {
    $user_messages = [];
    foreach ($input->getMessages() as $message) {
      if ($message instanceof ChatMessage && $message->getRole() === 'user') {
        $user_messages[] = $message;
      }
    }
    if ($user_messages === [] || $scan_all) {
      return $user_messages;
    }

    $last_index = array_key_last($user_messages);
    return [$user_messages[$last_index]];
  }

}
