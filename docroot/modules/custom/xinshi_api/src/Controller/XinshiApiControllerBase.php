<?php

namespace Drupal\xinshi_api\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;

/**
 * JSON responses and cache metadata shared by the api/v3 endpoints.
 */
abstract class XinshiApiControllerBase extends ControllerBase {

  /**
   * @var string[]
   */
  protected $cacheTags = [];

  /**
   * @var string[]
   */
  protected $cacheContexts = ['url', 'user.permissions'];

  /**
   * 缓存时间
   *
   * @var integer
   */
  protected $cacheMaxAge = Cache::PERMANENT;

  /**
   * Return cache tags.
   * @return array
   */
  public function getCacheTags(): array {
    return array_unique($this->cacheTags);
  }

  /**
   * Set cache tags.
   * @param array $cacheTags
   */
  public function setCacheTags(array $cacheTags): void {
    $this->cacheTags = $cacheTags;
  }

  /**
   * Add cache tags.
   * @param array $cacheTags
   */
  public function addCacheTags(array $cacheTags): void {
    $this->cacheTags = array_merge($this->cacheTags, $cacheTags);
  }

  /**
   * Add cache contexts.
   * @param array $cacheContexts
   */
  public function addCacheContexts(array $cacheContexts): void {
    $this->cacheContexts = array_merge($this->cacheContexts, $cacheContexts);
  }

  /**
   * @return string[]
   */
  public function getCacheContexts(): array {
    return array_unique($this->cacheContexts);
  }

  /**
   * @param string[] $cacheContexts
   */
  public function setCacheContexts(array $cacheContexts): void {
    $this->cacheContexts = $cacheContexts;
  }

  public function getCacheMaxAge() {
    return $this->cacheMaxAge;
  }

  public function setCacheMaxAge($cacheMaxAge) {
    $this->cacheMaxAge = $cacheMaxAge;
  }

  /**
   * @param $data
   * @return CacheableJsonResponse
   */
  protected function getResponse($data): CacheableJsonResponse {
    $response = new CacheableJsonResponse($data);
    $metadata = CacheableMetadata::createFromRenderArray(['#cache' => [
      'tags' => $this->getCacheTags(),
      'contexts' => $this->getCacheContexts(),
      'max-age' => $this->getCacheMaxAge(),
    ]]);
    if ($this->config('xinshi_api.settings')->get('debug')) {
      $metadata->setCacheMaxAge(0);
    }
    $response->addCacheableDependency($metadata);
    return $response;
  }
}
