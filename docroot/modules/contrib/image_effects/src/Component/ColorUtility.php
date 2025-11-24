<?php

declare(strict_types=1);

namespace Drupal\image_effects\Component;

use Drupal\Component\Utility\Color;

/**
 * Color handling methods for image_effects.
 */
abstract class ColorUtility {

  /**
   * Determine best match to over/underlay a defined color.
   *
   * Calculates UCCIR 601 luma of the entered color and returns a black or
   * white color to ensure readibility.
   *
   * @see http://en.wikipedia.org/wiki/Luma_video
   */
  public static function matchLuma(string $rgba, bool $soft = FALSE): string {
    $rgb = mb_substr($rgba, 0, 7);
    [$r, $g, $b] = array_values(Color::hexToRgb($rgb));
    $luma = 1 - (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    if ($luma < 0.5) {
      // Bright colors - black.
      $d = 0;
    }
    else {
      // Dark colors - white.
      $d = 255;
    }
    return Color::rgbToHex([$d, $d, $d]);
  }

  /**
   * Convert RGBA alpha to percent opacity.
   *
   * @param string $rgba
   *   RGBA hexadecimal.
   *
   * @return int
   *   Opacity as percentage (0 = transparent, 100 = fully opaque).
   */
  public static function rgbaToOpacity(string $rgba): int {
    if (!static::validateRgba($rgba)) {
      if (Color::validateHex($rgba)) {
        return 100;
      }
      throw new \InvalidArgumentException("Invalid color '$rgba' specified for " . __METHOD__);
    }
    return (int) floor(hexdec(substr($rgba, -2)) / 255 * 100);
  }

  /**
   * Convert percent opacity to hex alpha.
   *
   * @param int $value
   *   Opacity as percentage (0 = transparent, 100 = fully opaque).
   *
   * @return string|null
   *   Opacity as HEX (#00 = transparent, #FF = fully opaque).
   */
  public static function opacityToAlpha(int $value): ?string {
    if (!$value || $value < 0 || $value > 100) {
      return NULL;
    }
    return mb_strtoupper(str_pad(dechex((int) ceil($value / 100 * 255)), 2, '0', STR_PAD_LEFT));
  }

  /**
   * Validates whether a hexadecimal RGBA color value is syntactically correct.
   *
   * @param string $hex
   *   The hexadecimal string to validate. Must contain a leading '#'. Must use
   *   the long notation (i.e. '#RRGGBBAA').
   *
   * @return bool
   *   TRUE if $hex is valid or FALSE if it is not.
   */
  public static function validateRgba(string $hex): bool {
    return preg_match('/^#([0-9a-fA-F]{8})$/', $hex) === 1;
  }

}
