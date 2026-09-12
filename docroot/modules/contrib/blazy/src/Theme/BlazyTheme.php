<?php

namespace Drupal\blazy\Theme;

use Drupal\blazy\Hook\ThemeHooks;

/**
 * Provides theme-related alias methods to de-clutter Blazy.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 *
 * @todo at 4.x:
 * - remove all hook and preprocess methods
 * - convert it into non-service instance class
 * - implements BlazyThemeInterface containing any theme-related methods
 *   for D11 DI Hook contructor.
 * - maybe rename it to just Theme and ThemeInterface for being internal.
 */
class BlazyTheme {

  /**
   * Implements hook_theme().
   */
  public static function theme(): array {
    return ThemeHooks::theme();
  }

  /**
   * Overrides variables for blazy.html.twig templates.
   */
  public static function blazy(array &$variables): void {
    ThemeHooks::preprocessBlazy($variables);
  }

  /**
   * Overrides variables for field.html.twig templates.
   */
  public static function field(array &$variables): void {
    ThemeHooks::preprocessField($variables);
  }

  /**
   * Overrides variables for file-audio.html.twig templates.
   */
  public static function fileAudio(array &$variables): void {
    ThemeHooks::preprocessFileAudio($variables);
  }

  /**
   * Overrides variables for file-video.html.twig templates.
   */
  public static function fileVideo(array &$variables): void {
    ThemeHooks::preprocessFileVideo($variables);
  }

  /**
   * Overrides variables for responsive-image.html.twig templates.
   */
  public static function responsiveImage(array &$variables): void {
    ThemeHooks::preprocessResponsiveImage($variables);
  }

  /**
   * Overrides variables for media-oembed-iframe.html.twig templates.
   */
  public static function mediaOembedIframe(array &$variables): void {
    ThemeHooks::preprocessMediaOembedIframe($variables);
  }

}
