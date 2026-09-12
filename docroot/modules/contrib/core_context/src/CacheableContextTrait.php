<?php

namespace Drupal\core_context;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Plugin\Context\ContextInterface as CacheableContextInterface;

/**
 * Provides methods for working with contexts that can be cached.
 */
trait CacheableContextTrait {

  /**
   * Adds cache metadata to a set of contexts.
   *
   * @param \Drupal\Component\Plugin\Context\ContextInterface[] $contexts
   *   The contexts to which cache metadata should be added.
   * @param \Drupal\Core\Cache\CacheableDependencyInterface $cache_metadata
   *   The object carrying the cache metadata.
   *
   * @return \Drupal\Component\Plugin\Context\ContextInterface[]
   *   The passed contexts, with cache metadata added.
   */
  private function applyCaching(array $contexts, CacheableDependencyInterface $cache_metadata) {
    foreach ($contexts as $context) {
      if ($context instanceof CacheableContextInterface) {
        $context->addCacheableDependency($cache_metadata);
      }
    }
    return $contexts;
  }

}
