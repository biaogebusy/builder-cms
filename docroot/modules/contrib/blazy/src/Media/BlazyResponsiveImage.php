<?php

namespace Drupal\blazy\Media;

/**
 * Provides responsive image utilities.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 *
 * @todo deprecate and remove before or at 4.x.
 */
class BlazyResponsiveImage {

  /**
   * Alias for ResponsiveImage::breakpointManager().
   */
  public static function breakpointManager() {
    return ResponsiveImage::breakpointManager();
  }

  /**
   * Alias for ResponsiveImage::transformed().
   */
  public static function transformed(array &$settings): void {
    ResponsiveImage::transformed($settings);
  }

  /**
   * Alias for ResponsiveImage::background().
   */
  public static function background(array &$attributes, array &$settings): void {
    ResponsiveImage::background($attributes, $settings);
  }

  /**
   * Alias for ResponsiveImage::dimensions().
   */
  public static function dimensions(
    array &$settings,
    $resimage = NULL,
    $initial = FALSE,
  ): void {
    ResponsiveImage::dimensions($settings, $resimage, $initial);
  }

  /**
   * Alias for ResponsiveImage::styles().
   */
  public static function styles($resimage): array {
    return ResponsiveImage::styles($resimage);
  }

  /**
   * Alias for ResponsiveImage::fallback().
   */
  public static function fallback(array &$settings, $placeholder): void {
    ResponsiveImage::fallback($settings, $placeholder);
  }

  /**
   * Alias for ResponsiveImage::toStyle().
   */
  public static function toStyle(array $settings, $unstyled = FALSE): ?object {
    return ResponsiveImage::toStyle($settings, $unstyled);
  }

}
