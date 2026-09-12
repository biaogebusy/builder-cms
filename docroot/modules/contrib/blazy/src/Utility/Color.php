<?php

namespace Drupal\blazy\Utility;

use Drupal\Component\Utility\Color as BaseColor;

/**
 * Performs color conversions.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Please use the public method instead.
 */
final class Color extends BaseColor {

  /**
   * Parses a hexadecimal color string like '#abc' or '#aabbcc'.
   *
   * @param string $hex
   *   The hexadecimal color string to parse.
   * @param float|int|string|null $opacity
   *   Alpha channel between 0.0 and 1.0 (inclusive), or NULL for none.
   * @param bool $use_hex
   *   Whether to keep hex when no opacity is provided.
   *
   * @return string
   *   RGBA if opacity is provided, otherwise RGB or hex.
   *
   * @todo add union types at 4.x:
   * float|int|string|null $opacity.
   */
  public static function hexToRgba(
    string $hex,
    $opacity = NULL,
    bool $use_hex = TRUE,
  ): string {
    $rgb = array_values(self::hexToRgb($hex));

    // Opacity explicitly provided (including 0.0).
    $alpha = Type::normalizeFloat($opacity);
    if ($alpha !== NULL) {
      $rgb[] = $alpha;

      return 'rgba(' . implode(', ', $rgb) . ')';
    }

    return $use_hex
      ? $hex
      : 'rgb(' . implode(', ', $rgb) . ')';
  }

}
