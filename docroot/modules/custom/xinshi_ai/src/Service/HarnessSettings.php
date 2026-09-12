<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

/** Shared defaults and the explicit allowlist for chat task configuration. */
final class HarnessSettings {

  public const MAX_LIMIT = 9007199254740991;

  public const DEFAULTS = [
    'tools' => ['pages_enabled' => FALSE],
    'task_limits' => ['max_model_calls' => 40, 'max_tokens' => 200000],
  ];

  /** Missing settings on existing sites behave like a new installation. */
  public static function normalize(mixed $value): array {
    $result = self::DEFAULTS;
    if (!is_array($value)) {
      return $result;
    }
    $tools = $value['tools'] ?? NULL;
    if (is_array($tools) && is_bool($tools['pages_enabled'] ?? NULL)) {
      $result['tools']['pages_enabled'] = $tools['pages_enabled'];
    }
    $limits = $value['task_limits'] ?? NULL;
    if (is_array($limits)) {
      foreach ($result['task_limits'] as $key => $default) {
        $limit = $limits[$key] ?? NULL;
        if (is_int($limit) && $limit > 0 && $limit <= self::MAX_LIMIT) {
          $result['task_limits'][$key] = $limit;
        }
      }
    }
    return $result;
  }

}
