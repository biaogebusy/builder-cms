<?php

namespace Drupal\blazy\Media;

use Drupal\Component\Utility\UrlHelper;
use Drupal\blazy\Internals\Internals;
use Drupal\blazy\Internals\Path;
use Drupal\blazy\Utility\Sanitize;

// @todo at 4.x use Drupal\image\ImageStyleInterface;.
/**
 * A common Url utility helper.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 *
 * @todo tighten the callers, or leave it for the lazy.
 */
class Url {

  /**
   * Creates a relative or absolute web-accessible URL string.
   *
   * @param string $uri
   *   The file uri.
   * @param bool $relative
   *   Whether to return an relative or absolute URL.
   *
   * @return string
   *   Returns an absolute web-accessible URL string.
   */
  public static function create(string $uri, bool $relative = FALSE): string {
    if ($gen = Path::fileUrlGenerator()) {
      // @todo recheck ::generateAbsoluteString doesn't return web-accessible
      // protocol as expected, required by getimagesize to work correctly.
      return $relative
        ? $gen->generateString($uri)
        : $gen->generateAbsoluteString($uri);
    }
    return '';
  }

  /**
   * A wrapper for UrlHelper::isExternal() for the lazy.
   *
   * @param string|null $url
   *   The optional url to test.
   *
   * @return bool
   *   True if an external url.
   *
   * @todo add union types at 4.x: string|null $url
   */
  public static function isExternal($url): bool {
    return $url && UrlHelper::isExternal($url);
  }

  /**
   * Returns image URL with an optional image style.
   *
   * Addressed various sources:
   * - URL which should not be styled: animated gif, apng, svg, etc.
   * - UGC image URL, with likely invalid URI due to hard-coded markdown, etc.
   * - Responsive image vs. regular image style.
   *
   * Requires \Drupal\blazy\Internals\Internals::prepare().
   *
   * @param array $settings
   *   The settings.
   * @param \Drupal\image\ImageStyleInterface|null $style
   *   The optional image style instance.
   * @param string|null $uri
   *   The file uri.
   *
   * @return string
   *   Returns an absolute URL of a local file to a relative one.
   *
   * @see self::prepare()
   * @see self::background()
   * @see ResponsiveImage::background()
   *
   * @todo deprecate and remove fallbacks after another check, also settings
   * after migration.
   * @todo add union types at 4.x:
   * ImageStyleInterface|null $style
   * string|null $uri
   */
  public static function fromAny(
    array $settings,
    $style = NULL,
    $uri = NULL,
  ): string {
    $blazies = Internals::getBlazies($settings);
    $uri     = $uri ?: $blazies->get('image.uri', $settings['uri'] ?? '');
    $valid   = Uri::isValid($uri);
    $styled  = $valid && !$blazies->is('unstyled');
    $style   = $styled ? $style : NULL;
    $url     = $settings['image_url'] ?? '';
    $url     = $blazies->get('image.url') ?: $url;

    $options = [
      'unsafe' => $blazies->is('unsafe'),
      'url' => $url,
      'use_data_uri' => $blazies->filter('use_data_uri'),
    ];

    return self::fromUri($uri, $style, $options);
  }

  /**
   * Returns image URL with an optional image style.
   *
   * @param string|null $uri
   *   The file uri.
   * @param \Drupal\image\ImageStyleInterface|null $style
   *   The optional image style instance.
   * @param array $options
   *   The options: unsafe, use_data_uri.
   *
   * @return string
   *   Returns an absolute URL of a local file to a relative one.
   *
   * @todo add union types at 4.x:
   * string|null $uri, ImageStyleInterface|null $style.
   */
  public static function fromUri(
    $uri,
    $style = NULL,
    array $options = [],
  ): string {
    $unsafe   = $options['unsafe'] ?? TRUE;
    $data_uri = $options['use_data_uri'] ?? FALSE;
    $url      = Uri::transformRelative($uri, $style, $options);

    // Just in case, an attempted kidding gets in the way, relevant for UGC.
    // @todo re-check to completely remove data URI.
    if ($url && $unsafe) {
      $url = Sanitize::url($url, $data_uri);
    }

    return $url ?: '';
  }

}
