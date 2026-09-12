<?php

namespace Drupal\redirect;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;

/**
 * Cache a list of prefixes and whether they have redirects.
 */
class RedirectPrefixList {

  /**
   * Cache ID prefix.
   */
  protected const CID = 'redirect_prefix_list:';

  public function __construct(protected CacheBackendInterface $cache, protected EntityTypeManagerInterface $entityTypeManager) {

  }

  /**
   * Checks if any redirects use the given prefix.
   *
   * @param string $path_prefix
   *   The path prefix to check.
   *
   * @return bool
   *   Whether there are any matching redirects.
   */
  protected function resolveCacheMiss(string $path_prefix): bool {
    // Check if there are any redirects starting with this part.
    $ids = $this->entityTypeManager->getStorage('redirect')->getQuery()
      ->accessCheck(FALSE)
      ->condition('redirect_source.path', $path_prefix . '/', 'STARTS_WITH')
      ->condition('enabled', 1)
      ->range(0, 1)
      ->count()
      ->execute();
    return (bool) $ids;
  }

  /**
   * Returns whether there may be redirects for the given prefix.
   *
   * @param string $source_path
   *   The complete source path being checked.
   *
   * @return bool
   *   FALSE if there are no redirects, TRUE if there may be.
   */
  public function hasRedirectsWithPrefix(string $source_path): bool {
    if (str_contains($source_path, '/') && Settings::get('redirect_use_prefix_list', TRUE)) {
      $prefix = $this->getNormalizedPrefix($source_path);

      $cache = $this->cache->get(static::CID . $prefix);
      if ($cache) {
        return $cache->data;
      }
      else {
        $has_prefix = $this->resolveCacheMiss($prefix);
        $this->cache->set(static::CID . $prefix, $has_prefix, CacheBackendInterface::CACHE_PERMANENT);
        return $has_prefix;
      }
    }
    return TRUE;
  }

  /**
   * Updates the cache if it became stale by the addition of a new redirect.
   *
   * @param string $source_path
   *   The redirect source that now exists in the storage.
   */
  public function registerNewSource(string $source_path): void {
    if (str_contains($source_path, '/') && Settings::get('redirect_use_prefix_list', TRUE)) {
      $prefix = $this->getNormalizedPrefix($source_path);
      $cache = $this->cache->get(static::CID . $prefix);
      // The cache only needs to be updated if the prefix is already in the
      // cache as not having redirects.
      if ($cache && $cache->data === FALSE) {
        $this->cache->set(static::CID . $prefix, TRUE, CacheBackendInterface::CACHE_PERMANENT);
      }
    }
  }

  /**
   * Returns the normalized prefix used for the cache and lookup.
   *
   * @param string $source_path
   *   The source path.
   *
   * @return string
   *   The lowercased prefix.
   */
  protected function getNormalizedPrefix(string $source_path): string {
    return mb_strtolower(substr($source_path, 0, strpos($source_path, '/')));
  }

}
