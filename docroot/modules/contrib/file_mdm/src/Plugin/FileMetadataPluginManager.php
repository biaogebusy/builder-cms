<?php

namespace Drupal\file_mdm\Plugin;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin manager for FileMetadata plugins.
 */
class FileMetadataPluginManager extends DefaultPluginManager {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory) {
    parent::__construct('Plugin/FileMetadata', $namespaces, $module_handler, 'Drupal\file_mdm\Plugin\FileMetadataPluginInterface', 'Drupal\file_mdm\Plugin\Annotation\FileMetadata');
    $this->alterInfo('file_metadata_plugin_info');
    $this->setCacheBackend($cache_backend, 'file_metadata_plugins');
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    $plugin_definition = $this->getDefinition($plugin_id);
    $default_config = call_user_func($plugin_definition['class'] . '::defaultConfiguration');
    $configuration = $this->configFactory->get($plugin_definition['provider'] . '.file_metadata_plugin.' . $plugin_id)->get('configuration') ?: [];
    return parent::createInstance($plugin_id, NestedArray::mergeDeep($default_config, $configuration));
  }

}
