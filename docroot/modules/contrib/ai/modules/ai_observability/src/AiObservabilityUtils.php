<?php

namespace Drupal\ai_observability;

use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;

/**
 * Utility functions for AI observability module.
 *
 * @package Drupal\ai_observability
 */
class AiObservabilityUtils {

  /**
   * Converts AI output to a string representation for logging and tracing.
   *
   * @todo Get rid of this when the OutputInterface will have toString() method.
   *
   * @param mixed $output
   *   The AI output object.
   *
   * @return string
   *   The string representation of the AI output.
   */
  public static function aiOutputToString(mixed $output): string {
    if ($output instanceof ChatOutput) {
      $normalized = $output->getNormalized();
      if ($normalized instanceof ChatMessage) {
        return self::chatMessageToString($normalized);
      }
      else {
        return 'Streamed chat output cannot be converted to string directly.';
      }
    }
    else {
      return 'Output type ' . get_debug_type($output) . ' not supported for string conversion.';
    }
  }

  /**
   * Converts a ChatMessage to a string representation.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatMessage $message
   *   The ChatMessage instance.
   *
   * @return string
   *   The string representation of the ChatMessage.
   */
  public static function chatMessageToString(ChatMessage $message): string {
    $files_text = '';
    $files = $message->getFiles();
    if (!empty($files)) {
      $file_names = array_map(fn ($file) => $file->getFileName(), $files);
      $files_text = ' [Files: ' . implode(', ', $file_names) . ']';
    }
    return sprintf('%s: %s%s', $message->getRole(), $message->getText(), $files_text);
  }

  /**
   * Summarizes AI payload for logging and tracing.
   *
   * @param string $payload
   *   The AI payload in the string representation.
   * @param int $maxLength
   *   The maximum length of the summarized payload.
   *
   * @return string
   *   The stringified AI payload.
   */
  public static function summarizeAiPayloadData(string $payload, int $maxLength = 1024): string {
    if (mb_strlen($payload) <= $maxLength) {
      return $payload;
    }
    // If truncation is needed, insert '...' in the middle.
    $ellipsis = '[...]';
    $keep = $maxLength - mb_strlen($ellipsis);
    $start = (int) ceil($keep / 2);
    $end = (int) floor($keep / 2);
    return mb_substr($payload, 0, $start) . $ellipsis . mb_substr($payload, -$end);
  }

}
