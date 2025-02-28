<?php

namespace Drupal\blazy\Cache;

use Drupal\blazy\internals\Internals;
use Drupal\Core\Cache\Cache;

/**
 * Provides common cache utility static methods.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 *
 * @todo remove for \Drupal\blazy\Asset\Libraries methods at 3.x.
 */
class BlazyCache {

  /**
   * Return the available lightboxes, to be cached to avoid disk lookups.
   *
   * @todo remove for \Drupal\blazy\Asset\Libraries::getLightboxes() at 3.x.
   */
  public static function lightboxes($root): array {
    $lightboxes = ['flybox'];
    if (function_exists('colorbox_theme')) {
      $lightboxes[] = 'colorbox';
    }

    // @todo remove deprecated unmaintained photobox.
    // Most lightboxes are unmantained, only supports mostly used, or robust.
    $paths = [
      'photobox' => 'photobox/photobox/jquery.photobox.js',
      'mfp' => 'magnific-popup/dist/jquery.magnific-popup.min.js',
    ];

    foreach ($paths as $key => $path) {
      if (is_file($root . '/libraries/' . $path)) {
        $lightboxes[] = $key;
      }
    }
    return $lightboxes;
  }

  /**
   * Return the cache metadata common for all blazy-related modules.
   *
   * @todo remove for \Drupal\blazy\Asset\Libraries::getCacheMetadata() at 3.x.
   */
  public static function metadata(array $build = []): array {
    $manager   = Internals::service('blazy.manager');
    $settings  = Internals::toHashtag($build) ?: $build;
    $blazies   = Internals::verify($settings);
    $namespace = $blazies->get('namespace', 'blazy');
    $count     = $blazies->total() ?: $blazies->get('count', count($settings));
    $max_age   = $manager->config('cache.page.max_age', 'system.performance');
    $max_age   = empty($settings['cache']) ? $max_age : $settings['cache'];
    $id        = Internals::getHtmlId($namespace . $count);
    $id        = $blazies->get('css.id', $id);
    $id        = substr(md5($id), 0, 11);

    // Put them into cxahe.
    $cache             = [];
    $suffixes[]        = $count;
    $cache['tags']     = Cache::buildTags($namespace . ':' . $id, $suffixes, '.');
    $cache['contexts'] = ['languages', 'url.site'];
    $cache['max-age']  = $max_age;
    $cache['keys']     = $blazies->get('cache.metadata.keys', [$id]);

    if ($tags = $blazies->get('cache.metadata.tags', [])) {
      $cache['tags'] = Cache::mergeTags($cache['tags'], $tags);
    }

    return $cache;
  }

}
