<?php

namespace Drupal\xinshi_api\Plugin\rest\resource;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class XinshibResourceBase
 * @package Drupal\xinshi_api\Plugin\rest\resource
 */
class XinshibResourceBase extends ResourceBase {

  /**
   * @var array
   */
  protected $cacheTags = [];

  /**
   * @var ImmutableConfig
   */
  protected $config;

    /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->config = $container->get('config.factory')->get('xinshi_api.settings');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }


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
   * @param $data
   * @return ResourceResponse
   */
  protected function getResponse($data) {
    $response = new ResourceResponse($data);
    $response->getCacheableMetadata()->addCacheTags($this->getCacheTags());
    $response->getCacheableMetadata()->addCacheContexts(['url', 'user.permissions']);
    if ($this->config->get('debug')) {
      $response->getCacheableMetadata()->setCacheMaxAge(0);
    }
    return $response;
  }
}
