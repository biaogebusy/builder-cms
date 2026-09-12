<?php

namespace Drupal\blazy\Media;

/**
 * Provides image-related methods.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 *
 * @todo deprecate and remove before or at 4.x.
 */
class BlazyImage {

  /**
   * Alias for Image::background().
   */
  public static function prepare(array &$settings, $item = NULL, $uri = NULL): void {
    Image::prepare($settings, $item, $uri);
  }

  /**
   * Alias for Image::background().
   */
  public static function background(array $settings, $style = NULL): array {
    return Image::background($settings, $style);
  }

  /**
   * Alias for Image::cropDimensions().
   */
  public static function cropDimensions(array &$settings, $style): void {
    Image::cropDimensions($settings, $style);
  }

  /**
   * Alias for Image::dimensions().
   */
  public static function dimensions(array &$settings, $item, $uri, $initial = FALSE): array {
    return Image::dimensions($settings, $item, $uri, $initial);
  }

  /**
   * Alias for Image::fromAny().
   */
  public static function fromAny($object, array &$settings = []): ?object {
    return Image::fromAny($object, $settings);
  }

  /**
   * Alias for Image::fromContent().
   */
  public static function fromContent(array $options, $name = NULL): ?object {
    return Image::fromContent($options, $name);
  }

  /**
   * Alias for Image::isImage().
   */
  public static function isImage($item): bool {
    return Image::isImage($item);
  }

  /**
   * Alias for Image::isValid().
   */
  public static function isValidItem($item): bool {
    return Image::isValid($item);
  }

  /**
   * Alias for Image::item().
   */
  public static function item($item = NULL, array $options = [], $name = NULL): ?object {
    return Image::item($item, $options, $name);
  }

  /**
   * Alias for Image::styles().
   */
  public static function styles(array &$settings, $multiple = FALSE): void {
    Image::styles($settings, $multiple);
  }

  /**
   * Alias for Image::toArray().
   */
  public static function toArray($item): array {
    return Image::toArray($item);
  }

  /**
   * Alias for Image::transformDimensions().
   */
  public static function transformDimensions($style, $config, $uri = NULL): array {
    return Image::transformDimensions($style, $config, $uri);
  }

  /**
   * Alias for Url::fromAny().
   */
  public static function toUrl(array $settings, $style = NULL, $uri = NULL): string {
    return Url::fromAny($settings, $style, $uri);
  }

  /**
   * Alias for Url::fromUri().
   */
  public static function url($uri, $style = NULL, array $options = []): string {
    return Url::fromUri($uri, $style, $options);
  }

}
