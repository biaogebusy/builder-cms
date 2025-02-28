<?php

namespace Drupal\blazy;

use Drupal\blazy\Deprecated\BlazyDeprecatedTrait;
use Drupal\blazy\internals\Internals;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Theme\Attributes;
use Drupal\blazy\Utility\CheckItem;
use Drupal\blazy\Utility\Sanitize;

/**
 * Provides common public blazy utility and a few aliases for frequent methods.
 *
 * Was planned to be non-static since 1.x as blazy service, failed miserably
 * and removed at 2.x as service due to some design problems.
 * Since 2.17, we are preparing it for a service at 3.x, thus static methods
 * similar to BlazyInterface will need to be removed at 3.x so that it can be
 * made non-static and extends BlazyBase as a non-manager alternative. Those
 * deprecated methods are stored in BlazyDeprecatedTrait for easy removal.
 * Two reasons for the failures: no real motivations, and enjoying static
 * methods better. More reasons for re-enacting it as a service: a new
 * compelling motivation for blazy.skin service at 3.x for sub-modules
 * boilerplate reducers, and more cool kid features like flybox, hoverable
 * effects, skins etc.
 *
 * Using aliases allow Blazy to self-organize, or improve as needed. A good
 * sample is BlazyGrid relocation, or likely BlazySettings, etc. If you are
 * calling global methods marked as @internal, consider:
 *   - changing them to the replacements below, if any.
 *   - using the provided non-static manager services since most static classes
 *     were proven to change to non-static overtime to overcome static class
 *     limitations, or design problems. Some were moved into BlazyInterface
 *     since early 2.16.
 *
 * @todo refactor as a service at 3.x for non-manager alternative.
 */
class Blazy {

  // @todo remove at blazy:3.0.
  use BlazyDeprecatedTrait;

  /**
   * Alias for CheckItem::autoplay().
   */
  public static function autoplay($url, $check = TRUE): string {
    return CheckItem::autoplay($url, $check);
  }

  /**
   * Alias for Attributes::container().
   */
  public static function containerAttributes(array &$attributes, array $settings): void {
    Attributes::container($attributes, $settings);
  }

  /**
   * Alias for BlazyFile::createUrl().
   */
  public static function createUrl($uri, $relative = FALSE): string {
    return BlazyFile::createUrl($uri, $relative);
  }

  /**
   * Alias for BlazyEntity::settings().
   */
  public static function entitySettings(array &$settings, $entity): void {
    BlazyEntity::settings($settings, $entity);
  }

  /**
   * Alias for CheckItem::has().
   */
  public static function has($content, $needle) {
    return CheckItem::has($content, $needle);
  }

  /**
   * Initialize Blazy settings for convenience.
   */
  public static function init(array $data = []): array {
    return $data + BlazyDefault::htmlSettings();
  }

  /**
   * Alias for BlazySettings().
   */
  public static function initSettings(array $data = []): BlazySettings {
    return new BlazySettings($data);
  }

  /**
   * Return TRUE if an url is a data URI.
   */
  public static function isDataUri($url) {
    $url = trim($url ?: '');
    return $url && mb_substr($url, 0, 10) === 'data:image';
  }

  /**
   * Alias for BlazyFile::normalizeUri().
   */
  public static function normalizeUri($path): string {
    return BlazyFile::normalizeUri($path);
  }

  /**
   * Alias for Sanitize::attribute().
   */
  public static function sanitize(array $attributes, $escaped = TRUE, $lowercase = FALSE): array {
    return Sanitize::attribute($attributes, $escaped, $lowercase);
  }

  /**
   * Sanitize media input URL.
   */
  public static function sanitizeInputUrl($input): ?string {
    return Sanitize::inputUrl($input);
  }

  /**
   * In case we have SVG Sanitizer alternatives, provide one door check.
   */
  public static function svgSanitizerExists(): bool {
    return class_exists('\enshrined\svgSanitize\Sanitizer');
  }

  /**
   * Returns the translated entity if available.
   */
  public static function translated($entity, $langcode = NULL): object {
    if ($manager = Internals::service('blazy.manager')) {
      $entity = $manager->getTranslatedEntity($entity, $langcode);
    }
    return $entity;
  }

  /**
   * Alias for BlazyImage::transformDimensions().
   */
  public static function transformDimensions($style, $data, $uri = NULL): array {
    return BlazyImage::transformDimensions($style, $data, $uri);
  }

  /**
   * Alias for BlazyFile::transformRelative().
   */
  public static function transformRelative($uri, $style = NULL, array $options = []): string {
    return BlazyFile::transformRelative($uri, $style, $options);
  }

  /**
   * Alias for BlazyImage::toUrl().
   */
  public static function toUrl(array $settings, $style = NULL, $uri = NULL): string {
    return BlazyImage::toUrl($settings, $style, $uri);
  }

  /**
   * Alias for BlazyImage::url().
   */
  public static function url($uri, $style = NULL, array $options = []): string {
    return BlazyImage::url($uri, $style, $options);
  }

  /**
   * Alias for BlazyFile::isValidUri().
   */
  public static function isValidUri($uri): bool {
    return BlazyFile::isValidUri($uri);
  }

  /**
   * Alias for BlazyFile::uri().
   */
  public static function uri($item, array $settings = []): string {
    return BlazyFile::uri($item, $settings);
  }

  /**
   * Returns a module installed version based on `hook_update_VERSION`.
   *
   * @requires drupal:9.3.0, no need a fallback.
   */
  public static function version($module): int {
    if ($service = Internals::service('update.update_hook_registry')) {
      return (int) $service->getInstalledVersion((string) $module);
    }
    return 0;
  }

}
