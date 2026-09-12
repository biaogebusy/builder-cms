<?php

declare(strict_types=1);

namespace Drupal\blazy;

use Drupal\blazy\Internals\Entity;
use Drupal\blazy\Internals\Internals;
use Drupal\blazy\Theme\Attributes;
use Drupal\blazy\Media\Image;
use Drupal\blazy\Media\Uri;
use enshrined\svgSanitize\Sanitizer;

/**
 * Provides common public Blazy utilities and a limited set of aliases.
 *
 * This serves as a more maintainable class than Blazy for static calls
 * to replace and finally remove lingering Blazy static methods.
 *
 * This class acts as a small public façade to shield callers from internal
 * refactors and relocations. Aliases allow Blazy to reorganize or evolve
 * internal implementations over time without breaking existing integrations
 * (for example, the relocation of BlazyGrid or BlazySettings).
 *
 * If you are calling global methods marked as @internal, consider:
 *   - switching to the documented aliases provided here, when available.
 *   - using the appropriate injected manager or interface-based services,
 *     as static utilities may continue to be reduced in scope.
 */
final class BlazyApi {

  /**
   * Initialize Blazy settings for convenience.
   */
  public static function init(array $data = []): array {
    return $data + BlazyDefault::htmlSettings();
  }

  /**
   * Alias for Internals::fileExistsReplace().
   */
  public static function fileExistsReplace() {
    return Internals::fileExistsReplace();
  }

  /**
   * In case we have SVG Sanitizer alternatives, provide one door check.
   */
  public static function svgSanitizerExists(): bool {
    return class_exists(Sanitizer::class);
  }

  /**
   * Alias for Uri::normalize() or Image::normalizeUri().
   */
  public static function normalizeUri($uri): string {
    return Uri::normalize($uri);
  }

  /**
   * Alias for Image::uri().
   */
  public static function uri($item, array $settings = []): ?string {
    return Uri::fromImage($item, $settings);
  }

  /**
   * Alias for Image::transformDimensions().
   */
  public static function transformDimensions($style, $data, $uri = NULL): array {
    return Image::transformDimensions($style, $data, $uri);
  }

  /**
   * Alias for Uri::transformRelative().
   */
  public static function transformRelative($uri, $style = NULL, array $options = []): string {
    return Uri::transformRelative($uri, $style, $options);
  }

  /**
   * Alias for Container::attributes().
   */
  public static function containerAttributes(array &$attributes, array $settings): void {
    Attributes::container($attributes, $settings);
  }

  /**
   * Alias for Internals::blazy().
   */
  public static function manager(): ?BlazyManagerInterface {
    return Internals::blazy();
  }

  /**
   * Alias for Internals::has().
   *
   * @todo delete after submodules at 4.x.
   */
  public static function has($content, $needle): bool {
    return Internals::has($content, $needle);
  }

  /**
   * Alias for Internals::getBlazies().
   *
   * @todo delete after submodules at 4.x.
   */
  public static function getBlazies(array &$settings, bool $merge = FALSE, string $key = 'blazies'): BlazySettings {
    return Internals::getBlazies($settings, $merge, $key);
  }

  /**
   * Alias for Internals::init().
   *
   * @todo delete after submodules at 4.x.
   */
  public static function initSettings(array $data = []): BlazySettings {
    return Internals::init($data);
  }

  /**
   * Alias for Entity::settings().
   *
   * @todo delete after submodules at 4.x.
   */
  public static function entitySettings(array &$settings, $entity): void {
    Entity::settings($settings, $entity);
  }

}
