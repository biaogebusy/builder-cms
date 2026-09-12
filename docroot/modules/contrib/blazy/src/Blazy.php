<?php

namespace Drupal\blazy;

use Drupal\blazy\Internals\Entity;
use Drupal\blazy\Internals\Internals;
use Drupal\blazy\Theme\Attributes;
use Drupal\blazy\Utility\Sanitize;
use Drupal\blazy\Media\Image;
use Drupal\blazy\Media\Uri;
use Drupal\blazy\Media\Url;
use enshrined\svgSanitize\Sanitizer;

/**
 * Provides deprecated Blazy utilities and a limited set of aliases.
 *
 * Use Drupal\blazy\BlazyApi for similar methods where available instead.
 * "No replacement" meanings: obsolete at D11, has instance class replacement,
 * or considered as being internal and useless outside the ecosystem usage.
 *
 * @todo in 4.x:
 *   - Remove all static methods for DI at 4.x.
 *   - Make this core infrastructure layer with basic common methods.
 *   - Remove BlazyBase, keep BlazyInterface, and make it final.
 */
class Blazy extends BlazyBase {

  /**
   * Initialize Blazy settings for convenience.
   *
   * @todo leave it unchanged till 4.x changes blazy.api.php.
   */
  public static function init(array $data = []): array {
    @trigger_error('init is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. Use BlazyApi::init() instead. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return $data + BlazyDefault::htmlSettings();
  }

  /**
   * Alias for Internals::fileExistsReplace().
   */
  public static function fileExistsReplace() {
    @trigger_error('fileExistsReplace is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. Use BlazyApi::fileExistsReplace() instead. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Internals::fileExistsReplace();
  }

  /**
   * In case we have SVG Sanitizer alternatives, provide one door check.
   */
  public static function svgSanitizerExists(): bool {
    @trigger_error('svgSanitizerExists is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. Use BlazyApi::svgSanitizerExists() instead. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return class_exists(Sanitizer::class);
  }

  /**
   * Alias for Uri::normalize().
   */
  public static function normalizeUri($path): string {
    @trigger_error('normalizeUri is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. Use BlazyApi::normalizeUri() instead. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Uri::normalize($path);
  }

  /**
   * Alias for Uri::fromImage().
   */
  public static function uri($item, array $settings = []): string {
    @trigger_error('uri is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. Use BlazyApi::uri() instead. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Uri::fromImage($item, $settings);
  }

  /**
   * Alias for Image::transformDimensions().
   */
  public static function transformDimensions($style, $data, $uri = NULL): array {
    @trigger_error('transformDimensions is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. Use BlazyApi::transformDimensions() instead. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Image::transformDimensions($style, $data, $uri);
  }

  /**
   * Alias for Uri::transformRelative().
   */
  public static function transformRelative($uri, $style = NULL, array $options = []): string {
    @trigger_error('transformRelative is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. Use BlazyApi::transformRelative() instead. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Uri::transformRelative($uri, $style, $options);
  }

  /**
   * Alias for Attributes::container().
   */
  public static function containerAttributes(array &$attributes, array $settings): void {
    @trigger_error('containerAttributes is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. Use BlazyApi::containerAttributes() instead. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    Attributes::container($attributes, $settings);
  }

  /**
   * Alias for Url::create().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3575429
   */
  public static function createUrl($uri, $relative = FALSE): string {
    @trigger_error('createUrl is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement till 4.x. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Url::create($uri, $relative);
  }

  /**
   * Alias for Entity::settings().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3575429
   */
  public static function entitySettings(array &$settings, $entity): void {
    @trigger_error('entitySettings is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement till 4.x. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    Entity::settings($settings, $entity);
  }

  /**
   * Alias for Internals::formatTitle().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3575429
   */
  public static function formatTitle($value, $url, array $settings): array {
    @trigger_error('formatTitle is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement till 4.x. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Internals::formatTitle($value, $url, $settings);
  }

  /**
   * Alias for Internals::getBlazies().
   */
  public static function getBlazies(array &$settings, bool $merge = FALSE, string $key = 'blazies'): BlazySettings {
    @trigger_error('getBlazies is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement till 4.x. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Internals::getBlazies($settings, $merge, $key);
  }

  /**
   * Alias for Internals::service().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3575429
   */
  public static function getService(string $key) {
    @trigger_error('getService is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. Use \Drupal::service() or BlazyManager::service() instead. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Internals::service($key);
  }

  /**
   * Alias for Internals::has().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3575429
   */
  public static function has($content, $needle): bool {
    @trigger_error('has is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement till 4.x. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Internals::has($content, $needle);
  }

  /**
   * Alias for Internals::init().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3575429
   */
  public static function initSettings(array $data = []): BlazySettings {
    @trigger_error('initSettings is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement till 4.x. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Internals::init($data);
  }

  /**
   * Alias for Sanitize::attribute().
   */
  public static function sanitize(array $attributes, $escaped = TRUE, $lowercase = FALSE): array {
    @trigger_error('sanitize is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Sanitize::attribute($attributes, $escaped, $lowercase);
  }

  /**
   * Sanitize media input URL.
   */
  public static function sanitizeInputUrl($input, array $options = []): string {
    @trigger_error('sanitizeInputUrl is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Sanitize::inputUrl($input, $options);
  }

  /**
   * Alias for Url::fromAny().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3575429
   */
  public static function toUrl(array $settings, $style = NULL, $uri = NULL): string {
    @trigger_error('toUrl is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement till 4.x. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Url::fromAny($settings, $style, $uri);
  }

  /**
   * Alias for Url::fromUri().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3575429
   */
  public static function url($uri, $style = NULL, array $options = []): string {
    @trigger_error('url is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement till 4.x. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Url::fromUri($uri, $style, $options);
  }

  /**
   * Alias for Uri::isDataUri().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3575429
   */
  public static function isDataUri($url): bool {
    @trigger_error('isDataUri is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Uri::isDataUri($url);
  }

  /**
   * Alias for Uri::isValid().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3575429
   */
  public static function isValidUri($uri): bool {
    @trigger_error('isValidUri is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement till 4.x. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Uri::isValid($uri);
  }

  /**
   * Alias for Entity::translated().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3575429
   */
  public static function translated($entity, $langcode = NULL): object {
    @trigger_error('translated is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement till 4.x. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Entity::translated($entity, $langcode);
  }

  /**
   * Alias for Internals::version().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3575429
   */
  public static function version($module): int {
    @trigger_error('version is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement, always set once. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Internals::version($module);
  }

  /**
   * Alias for Internals::autoplay().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3575429
   */
  public static function autoplay($url, $check = TRUE): string {
    @trigger_error('autoplay is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement due to being internal. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Internals::autoplay($url, $check);
  }

  /**
   * Alias for Internals::versionGreaterThan().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3575429
   */
  public static function versionGreaterThan($deprecatedVersion): bool {
    @trigger_error('versionGreaterThan is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement due to being obselete at D11. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Internals::versionGreaterThan($deprecatedVersion);
  }

  /**
   * Alias for Internals::versionGreaterThan().
   *
   * @todo deprecate and remove before or at 4.x.
   * @see https://www.drupal.org/node/3575429
   */
  public static function backwardsCompatibleCall(
    string $deprecatedVersion,
    callable $currentCallable,
    callable $deprecatedCallable,
  ): mixed {
    @trigger_error('backwardsCompatibleCall is deprecated in blazy:3.0.17 and is removed from blazy:4.0.0. No replacement due to being obselete at D11. See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
    return Internals::backwardsCompatibleCall($deprecatedVersion, $currentCallable, $deprecatedCallable);
  }

}
