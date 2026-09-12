<?php

namespace Drupal\blazy\Utility;

/**
 * Provides common type utilities.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Please use the public method instead.
 */
final class Type {

  /**
   * Normalize potential mixed values.
   *
   * @param mixed $value
   *   The value.
   * @param bool $default
   *   The default value.
   *
   * @return bool
   *   Returns TRUE or FALSE.
   *
   * @todo add mixed param at 4.x.
   */
  public static function normalizeBool($value, bool $default = FALSE): bool {
    if (is_bool($value)) {
      return $value;
    }

    $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $bool ?? $default;
  }

  /**
   * Normalize mixed input into valid float or null.
   *
   * @param float|int|string|null $value
   *   Alpha channel between 0.0 and 1.0 (inclusive), or NULL for none.
   *
   * @return float|null
   *   The converted value if valid, otherwise null.
   *
   * @todo add union types at 4.x:
   * float|int|string|null $value.
   */
  public static function normalizeFloat($value): ?float {
    if ($value === NULL || $value === '') {
      return NULL;
    }

    if (is_string($value)) {
      if (!is_numeric($value)) {
        return NULL;
      }
      $value = (float) $value;
    }

    return max(0.0, min(1.0, (float) $value));
  }

}
