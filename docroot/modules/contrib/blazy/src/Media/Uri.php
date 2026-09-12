<?php

namespace Drupal\blazy\Media;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\image\ImageStyleInterface;
use Drupal\blazy\Internals\Internals;
use Drupal\blazy\Internals\Path;

/**
 * A common URI or mixed string URI/URL utility helper.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 *
 * @todo tighten the callers, or leave it for the lazy.
 */
class Uri {

  /**
   * Return TRUE if an url is a data URI with lazy check upstream.
   *
   * @param string|null $url
   *   The optional URI if differs from main image, such as thumbnail URI.
   *
   * @return bool
   *   True a data URI.
   *
   * @todo add union types at 4.x: string|null $url.
   */
  public static function isDataUri($url): bool {
    $url = trim($url ?: '');
    if ($url === '') {
      return FALSE;
    }

    // ASCII-safe, no mbstring (mb_substr) dependency.
    // mb_substr($url, 0, 10) === 'data:image';.
    return stripos($url, 'data:image') === 0;
  }

  /**
   * Returns TRUE if an SVG URI with lazy check upstream.
   *
   * @param string|null $uri
   *   The optional URI if differs from main image, such as thumbnail URI.
   *
   * @return bool
   *   True if an SVG file.
   *
   * @todo add union types at 4.x: string|null $uri.
   */
  public static function isSvg($uri): bool {
    // Some guy uploaded images without extensions, seen at wildlife.
    if ($uri && $ext = pathinfo($uri, PATHINFO_EXTENSION)) {
      // Some other guy put CAPITALIZED image extensions for real.
      $ext = strtolower($ext);
      return $ext == 'svg';
    }
    return FALSE;
  }

  /**
   * Returns URI from the given image URL with lazy check upstream.
   *
   * Relevant for unmanaged/ UGC files.
   * Converts `/sites/default/files/image.jpg` into `public://image.jpg`.
   *
   * @param string|null $url
   *   The url to test.
   *
   * @return string|null
   *   The public or private URI, or null.
   *
   * @todo re-check if core has this type of conversion.
   * @todo add union types at 4.x: string|null $url.
   */
  public static function build($url): ?string {
    $manager = Internals::blazy();
    if (!$url || !$manager) {
      return NULL;
    }

    if (!self::isExternal($url)
      && $normal_path = UrlHelper::parse($url)['path']) {

      // If the request has a base path, remove it from the beginning of the
      // normal path as it should not be included in the URI.
      $base_path = \Drupal::request()->getBasePath();
      if ($base_path && mb_strpos($normal_path, $base_path) === 0) {
        $normal_path = str_replace($base_path, '', $normal_path);
      }

      $scheme = $manager->config('default_scheme', 'system.file');

      $active_path = $scheme == 'public'
        ? PublicStream::basePath()
        : Settings::get('file_private_path');

      // Only concerns for the correct URI, not image URL which is already being
      // displayed via SRC attribute. Don't bother language prefixes for IMG.
      if ($active_path && mb_strpos($normal_path, $active_path) !== FALSE) {
        $path = str_replace($active_path, '', $normal_path);
        return self::normalize($path);
      }
    }
    return NULL;
  }

  /**
   * Alias for Url::isExternal() in case confused.
   *
   * @param string|null $url
   *   The url to test.
   *
   * @return bool
   *   True if external url.
   *
   * @todo add union types at 4.x: string|null $url.
   */
  public static function isExternal($url): bool {
    return Url::isExternal($url);
  }

  /**
   * Determines if the URI has a valid scheme for file API operations.
   *
   * With lazy check upstream.
   *
   * @param string|null $uri
   *   The URI to be tested.
   *
   * @return bool
   *   TRUE if the URI is valid.
   *
   * @todo add union types at 4.x: string|null $uri.
   */
  public static function isValid($uri): bool {
    if ($uri && $manager = Path::streamWrapperManager()) {
      return $manager->isValidUri($uri);
    }
    return FALSE;
  }

  /**
   * Normalizes URI for BlazyFilter URLs, etc., hardly formatters.
   *
   * With lazy check upstream.
   *
   * @param string|null $path
   *   The optional URI if differs from main image, such as thumbnail URI.
   *
   * @return string
   *   The normalized URI.
   *
   * @todo move it into DI instance class.
   * @todo add union types at 4.x: string|null $path.
   */
  public static function normalize($path): string {
    $uri = $path ?: '';
    $manager = Internals::blazy();

    if (!$uri || !$manager) {
      return $uri;
    }

    if ($stream = Path::streamWrapperManager()) {
      // The double slash was from ::build().
      if (substr($uri, 0, 2) === '//') {
        $scheme = $manager->config('default_scheme', 'system.file');
        $uri = $scheme . ':' . $uri;
      }
      $uri = $stream->normalizeUri($uri);
    }
    return $uri;
  }

  /**
   * Returns URI from image item, fake or valid one, no problem.
   *
   * With lazy check upstream.
   *
   * @param object|null $item
   *   The given image item.
   * @param array $settings
   *   The settings being passed if provided.
   *
   * @return string|null
   *   The URI or null.
   *
   * @todo add union types at 4.x: object|null $item.
   */
  public static function fromImage(
    $item,
    array $settings = [],
  ): ?string {
    $uri = NULL;

    if (Image::isValid($item)) {
      $file = $item->entity ?? NULL;
      $uri = $item->uri ?? NULL;
      // The ::getFileUri() may point to local video, not image URI.
      $uri = $uri ?: (File::isValid($file) ? $file->getFileUri() : NULL);
    }

    // No file API with unmanaged files here: hard-coded UGC, legacy VEF.
    if (!$uri && $settings) {
      $blazies = Internals::getBlazies($settings);
      $uri = $blazies->get('image.uri');
    }

    // @todo deprecate and remove settings.uri, deprecared, must be BC.
    return $uri ?: ($settings['uri'] ?? NULL);
  }

  /**
   * Returns web-accessible URI if an invalid is given.
   *
   * @param string $uri
   *   The optional URI if differs from main image, such as thumbnail URI.
   *
   * @return string
   *   The accessible URI.
   */
  public static function toAccessibleUri(string $uri): string {
    $abs = $uri;
    // Must be valid URI, or web-accessible url, not: /modules|themes/...
    if (!self::isValid($abs) && substr($abs, 0, 1) == '/') {
      if ($request = Path::requestStack()) {
        $abs = $request->getCurrentRequest()->getSchemeAndHttpHost() . $abs;
      }
    }
    return $abs;
  }

  /**
   * Transforms an absolute URL of a local file to a relative URL.
   *
   * With lazy check upstream.
   * Blazy Filter or OEmbed may pass mixed (external) URI upstream.
   *
   * @param string|null $uri
   *   The file uri.
   * @param \Drupal\image\ImageStyleInterface|null $style
   *   The optional image style instance.
   * @param array $options
   *   The options: default url.
   *
   * @return string
   *   Returns an absolute URL of a local file to a relative one.
   *
   * @todo add union types at 4.x:
   * string|null $uri, ImageStyleInterface|null $style.
   * @see BlazyOEmbed::getThumbnail()
   * @see BlazyFilter::getImageItemFromImageSrc()
   */
  public static function transformRelative(
    $uri,
    $style = NULL,
    array $options = [],
  ): string {
    $uri = $uri ?: '';
    $url = $options['url'] ?? '';

    if (!$uri) {
      return $url;
    }

    // Returns as is if an external URL: UCG or external OEmbed image URL.
    if (self::isExternal($uri)) {
      return $uri;
    }

    if (self::isExternal($url)) {
      return $url;
    }

    // If valid URI, use image style, or as is, and make it relative path.
    if (self::isValid($uri)) {
      $stylable = $style instanceof ImageStyleInterface && !Uri::isSvg($uri);
      $url = $stylable ? $style->buildUrl($uri) : Url::create($uri);

      if ($gen = Path::fileUrlGenerator()) {
        $url = $gen->transformRelative($url);
      }
    }

    // If transform failed, returns default URL, or URI as is.
    return $url ?: $uri;
  }

}
