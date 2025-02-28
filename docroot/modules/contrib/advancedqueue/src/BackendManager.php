<?php

namespace Drupal\advancedqueue;

use Drupal\advancedqueue\Annotation\AdvancedQueueBackend;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\BackendInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages backend plugins.
 */
class BackendManager extends DefaultPluginManager {

  /**
   * Default values for each backend plugin.
   *
   * @var array
   */
  protected $defaults = [
    'id' => '',
    'label' => '',
  ];

  /**
   * Constructs a new BackendManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AdvancedQueue/Backend', $namespaces, $module_handler, BackendInterface::class, AdvancedQueueBackend::class);

    $this->alterInfo('advancedqueue_backend_info');
    $this->setCacheBackend($cache_backend, 'advancedqueue_backend_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);

    foreach (['id', 'label'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new PluginException(sprintf('The backend "%s" must define the "%s" property.', $plugin_id, $required_property));
      }
    }
  }

}
