<?php

namespace Drupal\shs\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Cacheable dependency object for term data.
 */
class ShsTermCacheDependency implements CacheableDependencyInterface {

  /**
   * An array of cache contexts.
   *
   * @var array
   */
  protected $contexts;

  /**
   * An array of cache tags.
   *
   * @var array
   */
  protected $tags;

  /**
   * The cache item maximum age ('max-age' property).
   *
   * @var int
   */
  protected $maxAge;

  /**
   * {@inheritdoc}
   */
  public function __construct($tags = []) {
    $this->contexts = ['languages:language_interface', 'user.roles'];
    $this->tags = Cache::mergeTags(['taxonomy_term_values'], $tags);
    $this->maxAge = Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags($this->tags, \Drupal::entityTypeManager()->getDefinition('taxonomy_term')->getListCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return $this->maxAge;
  }

}
