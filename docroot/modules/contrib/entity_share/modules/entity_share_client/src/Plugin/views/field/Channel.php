<?php

namespace Drupal\entity_share_client\Plugin\views\field;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views field plugin for the channel.
 */
#[ViewsField('entity_share_client_channel')]
class Channel extends FieldPluginBase {

  /**
   * The channel labels from the cache.
   *
   * @var array
   */
  protected $channelLabels = [];

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('cache.data'),
    );
  }

  /**
   * Creates a Channel instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CacheBackendInterface $cache_backend,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cacheBackend = $cache_backend;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);

    if (!empty($this->channelLabels[$value])) {
      $value = $this->channelLabels[$value];
    }

    return $this->sanitizeValue($value);
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    // Load the channel labels from the cache.
    // The RemoteManager populates this cache when the entity pull form is used.
    if ($cache_item = $this->cacheBackend->get('entity_share_client:channels')) {
      $this->channelLabels = array_merge(...array_values($cache_item->data));
    }
  }

}
