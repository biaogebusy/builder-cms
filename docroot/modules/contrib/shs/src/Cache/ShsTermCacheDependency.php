<?php

namespace Drupal\shs\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Language\LanguageInterface;

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
   * The taxonomy vocabulary identifier.
   *
   * @var string|null
   */
  protected $bundle;

  /**
   * The cache item maximum age ('max-age' property).
   *
   * @var int
   */
  protected $maxAge;

  /**
   * {@inheritdoc}
   */
  public function __construct($tags = [], $bundle = NULL) {
    $this->contexts = array_merge(['languages:' . LanguageInterface::TYPE_CONTENT, 'user.roles']);
    $this->tags = Cache::mergeTags(['taxonomy_term_values'], $tags);
    $this->bundle = $bundle;
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
    $entity_type = \Drupal::entityTypeManager()
      ->getDefinition('taxonomy_term');

    $list_cache_tags = $entity_type->getListCacheTags();

    if ($this->bundle !== NULL) {
      $list_cache_tags = Cache::mergeTags(
        $list_cache_tags,
        $entity_type->getBundleListCacheTags($this->bundle),
      );
    }

    return Cache::mergeTags($this->tags, $list_cache_tags);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return $this->maxAge;
  }

}
