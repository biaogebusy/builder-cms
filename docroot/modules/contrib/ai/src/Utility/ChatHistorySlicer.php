<?php

declare(strict_types=1);

namespace Drupal\ai\Utility;

use Drupal\ai\OperationType\Chat\ChatMessage;

/**
 * Slices chat histories without breaking tool call pairs.
 *
 * Providers require every tool call to be answered by a directly following tool
 * result message and reject a request where that pairing is broken. Slicing by
 * message count can break it, so these helpers group the history into atomic
 * blocks first, where a block is either a complete tool call round trip or any
 * other single message.
 */
final class ChatHistorySlicer {

  /**
   * The role that providers use for tool result messages.
   */
  public const TOOL_ROLE = 'tool';

  /**
   * Checks if a message requests one or more tool calls.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatMessage $message
   *   The message to check.
   *
   * @return bool
   *   TRUE if the message carries tool calls.
   */
  public static function hasToolCalls(ChatMessage $message): bool {
    // Streamed responses always set the tools, so this can be an empty array.
    return !empty($message->getTools());
  }

  /**
   * Checks if a message is the result of a tool call.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatMessage $message
   *   The message to check.
   *
   * @return bool
   *   TRUE if the message answers a tool call.
   */
  public static function isToolResult(ChatMessage $message): bool {
    // The tool id can be missing, and providers render a tool call id for any
    // message that has one, so both have to be checked.
    return $message->getRole() === self::TOOL_ROLE || !empty($message->getToolsId());
  }

  /**
   * Checks if a message can stand on its own in a chat history.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatMessage $message
   *   The message to check.
   *
   * @return bool
   *   TRUE if the message neither requests nor answers a tool call.
   */
  public static function isStandalone(ChatMessage $message): bool {
    return !self::isToolResult($message) && !self::hasToolCalls($message);
  }

  /**
   * Groups a chat history into atomic blocks.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatMessage[] $messages
   *   The chat history to group.
   *
   * @return array[]
   *   A list of blocks in the order of the given chat history. Each block is an
   *   array with the following keys:
   *   - messages: The messages of the block.
   *   - requests: The amount of tool calls the block requests.
   *   - answers: The amount of tool results the block holds.
   *   - keepable: FALSE if the block can never be sent to a provider.
   */
  public static function groupIntoBlocks(array $messages): array {
    $blocks = [];
    $open = NULL;
    foreach (array_values($messages) as $message) {
      if (self::isToolResult($message)) {
        // A message that answers and requests tool calls at the same time can
        // not be rendered into a valid request.
        if (self::hasToolCalls($message)) {
          $blocks[] = [
            'messages' => [$message],
            'requests' => 0,
            'answers' => 0,
            'keepable' => FALSE,
          ];
          $open = NULL;
          continue;
        }
        // A tool result without an open loop has lost its tool call.
        if ($open === NULL) {
          $blocks[] = [
            'messages' => [$message],
            'requests' => 0,
            'answers' => 1,
            'keepable' => FALSE,
          ];
          continue;
        }
        $blocks[$open]['messages'][] = $message;
        $blocks[$open]['answers']++;
        $blocks[$open]['keepable'] = $blocks[$open]['answers'] >= $blocks[$open]['requests'];
        continue;
      }
      // Tool results have to directly follow their tool call, so any other
      // message closes an open loop.
      $open = NULL;
      $requests = count($message->getTools() ?? []);
      $blocks[] = [
        'messages' => [$message],
        'requests' => $requests,
        'answers' => 0,
        'keepable' => $requests === 0,
      ];
      if ($requests > 0) {
        $open = array_key_last($blocks);
      }
    }
    return $blocks;
  }

  /**
   * Slices the tail of a chat history by complete tool loops.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatMessage[] $messages
   *   The chat history to slice.
   * @param int $loops_to_keep
   *   How many of the most recent blocks to keep. Zero keeps nothing.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage[]
   *   The sliced chat history, with sequential keys. It never holds a tool
   *   result without its tool call, or a tool call that is not fully answered.
   */
  public static function sliceLastToolLoops(array $messages, int $loops_to_keep): array {
    if ($loops_to_keep <= 0) {
      return [];
    }
    return self::flatten(array_slice(self::keepableBlocks($messages), -$loops_to_keep));
  }

  /**
   * Rewrites a chat history as a summary message plus the most recent loops.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatMessage[] $messages
   *   The original chat history.
   * @param \Drupal\ai\OperationType\Chat\ChatMessage $summary
   *   The summary message to place in front of the kept tool loops.
   * @param int $loops_to_keep
   *   How many of the most recent tool loops to keep.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage[]
   *   The first message of the original history, followed by the summary
   *   message and the kept tool loops. The first message is only kept if it can
   *   stand on its own, and it is never repeated inside the slice.
   */
  public static function sliceWithSummary(array $messages, ChatMessage $summary, int $loops_to_keep): array {
    $sliced = self::sliceLastToolLoops($messages, $loops_to_keep);
    $first = array_values($messages)[0] ?? NULL;
    // The first message is moved away from the messages around it, so it can
    // only be kept if it does not need one of them.
    $keep_first = $first instanceof ChatMessage && self::isStandalone($first);
    // A slice is the tail of the history, so the first message can only be at
    // the start of it. Take it out here to not repeat it below.
    if ($keep_first && ($sliced[0] ?? NULL) === $first) {
      array_shift($sliced);
    }
    array_unshift($sliced, $summary);
    if ($keep_first) {
      array_unshift($sliced, $first);
    }
    return $sliced;
  }

  /**
   * Gets the blocks of a chat history that can be sent to a provider.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatMessage[] $messages
   *   The chat history to group.
   *
   * @return array[]
   *   The keepable blocks, with sequential keys.
   */
  private static function keepableBlocks(array $messages): array {
    return array_values(array_filter(
      self::groupIntoBlocks($messages),
      static fn (array $block): bool => $block['keepable'],
    ));
  }

  /**
   * Flattens a list of blocks back into a list of messages.
   *
   * @param array[] $blocks
   *   The blocks to flatten.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage[]
   *   The messages, with sequential keys.
   */
  private static function flatten(array $blocks): array {
    $messages = [];
    foreach ($blocks as $block) {
      foreach ($block['messages'] as $message) {
        $messages[] = $message;
      }
    }
    return $messages;
  }

}
