<?php

namespace Drupal\blazy\Internals;

use Drupal\Component\Utility\UrlHelper;
use Drupal\blazy\BlazySettings;
use Drupal\blazy\Media\Provider\Youtube;
use Drupal\blazy\Utility\Sanitize;

/**
 * Provides internal non-reusable blazy utilities.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class Multimedia extends Settings {

  /**
   * Provides autoplay URL for lightbox nested iframes to save another click.
   */
  public static function autoplay($url, $check = TRUE): string {
    $func = function ($str, $key) {
      $format1 = '%s&%s=1';
      $first = sprintf($format1, $str, $key);
      $format2 = '%s?%s=1';
      $last = sprintf($format2, $str, $key);

      return self::has($str, '?') ? $first : $last;
    };

    // It doesn't cover all providers, but few, no biggies till needed.
    if (!self::has($url, 'autoplay')
      || self::has($url, 'autoplay=0')) {
      $key = self::has($url, 'soundcloud') ? 'auto_play' : 'autoplay';
      return $func($url, $key);
    }

    // @todo recheck if any side effect/ double escape to cdn/ valid input.
    return $check ? UrlHelper::stripDangerousProtocols($url) : $url;
  }

  /**
   * Returns the expected/ corrected input URL.
   *
   * @param string $input
   *   The given url.
   *
   * @return string
   *   The input url.
   */
  public static function correct($input): ?string {
    // If you bang your head around why suddenly Instagram failed, this is it.
    // Only relevant for VEF, not core, in case ::toEmbedUrl() is by-passed:
    if ($input && strpos($input, '//instagram') !== FALSE) {
      $input = str_replace('//instagram', '//www.instagram', $input);
    }
    return $input;
  }

  /**
   * Checks if a provider can not use aspect ratio due to anti-mainstream sizes.
   */
  public static function irrational($provider): bool {
    return in_array($provider ?: 'x', [
      'd500px',
      'flickr',
      'instagram',
      'oembed:instagram',
      'pinterest',
      'twitter',
    ]);
  }

  /**
   * Disables linkable Pinterest, Twitter, etc.
   *
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The blazies instance.
   *
   * @return bool
   *   Whether the media content can be linked.
   *
   * @todo refine or excludes other providers that should not be linked.
   */
  public static function linkable($blazies): bool {
    if ($provider = $blazies->get('media.provider')) {
      if (self::irrational($provider) || in_array($provider, ['facebook'])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Provider sometimes NULL when called by sub-modules, not Blazy.
   *
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The blazies instance.
   * @param string|null $provider
   *   The provider name.
   *
   * @return string|null
   *   The provider name or NULL.
   *
   * @fixme somewhere else.
   */
  public static function provider($blazies, $provider = NULL): ?string {
    if (!$provider) {
      $provider = $blazies->get('media.provider');

      // Anything will do, no problem, no validation is required for CSS class.
      if (!$provider && $input = $blazies->get('media.input_url')) {
        // parse_url() may return NULL for PHP_URL_HOST (e.g. schemeless or
        // malformed URLs). Avoid passing NULL to str_ireplace() (deprecated on
        // PHP 8.1+). Try a safe fallback for schemeless URLs.
        $host = parse_url($input, PHP_URL_HOST);

        // Fallback: support schemeless URLs like "example.com/path".
        if (!$host && is_string($input)) {
          $host = parse_url('https://' . ltrim($input, '/'), PHP_URL_HOST);
        }

        // Only run replacements when a valid host string is available.
        if (is_string($host) && $host !== '') {
          $provider = str_ireplace(['www.', '.com'], '', $host);
        }
      }
    }
    return $provider;
  }

  /**
   * Alias for Youtube::fromEmbed().
   *
   * @param string|null $input
   *   The input URL.
   * @param bool $privacy
   *   Whether to enforce privacy.
   *
   * @return string|null
   *   The youtube URL.
   */
  public static function youtube($input, bool $privacy = FALSE): ?string {
    return Youtube::fromEmbed($input, $privacy);
  }

  /**
   * Checks if it is a video.
   *
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The blazies instance.
   *
   * @return bool
   *   Whether the media is video or not.
   */
  public static function isVideo($blazies): bool {
    if ($blazies->get('media.input_url')) {
      $type = $blazies->get('media.resource.type') ?: $blazies->get('media.type');
      return $type == 'video';
    }
    return FALSE;
  }

  /**
   * Modifies settings to support iframes.
   *
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The blazies instance.
   * @param string|null $src
   *   The input URL.
   * @param bool $sanitized
   *   Whether to sanitized.
   *
   * @return \Drupal\blazy\BlazySettings
   *   The BlazySettings object.
   */
  public static function toPlayable($blazies, $src = NULL, bool $sanitized = FALSE): BlazySettings {
    if ($src) {
      if (!$sanitized) {
        $src = Sanitize::url($src);
        $sanitized = TRUE;
      }

      $blazies->set('media.embed_url', $src)
        ->set('media.escaped', $sanitized);
    }

    return $blazies->set('is.iframeable', TRUE)
      ->set('is.playable', TRUE)
      ->set('is.multimedia', TRUE)
      ->set('use.content', FALSE)
      ->set('libs.media', TRUE);
  }

}
