<?php

namespace Drupal\charts;

use Drupal\charts\Plugin\chart\Library\LibraryRetrieverTrait;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Chart Manager.
 *
 * Provides the Chart plugin manager and manages discovery and instantiation of
 * chart plugins.
 */
class ChartManager extends DefaultPluginManager {

  use LibraryRetrieverTrait;

  /**
   * Constructor for ChartManager objects.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Config\ConfigFactoryInterface|null $configFactory
   *   The config factory.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, protected ?ConfigFactoryInterface $configFactory = NULL) {
    parent::__construct('Plugin/chart/Library', $namespaces, $module_handler, 'Drupal\charts\Plugin\chart\Library\ChartInterface', 'Drupal\charts\Attribute\Chart', 'Drupal\charts\Annotation\Chart');

    if (!$this->configFactory) {
      // @phpstan-ignore-next-line
      $this->configFactory = \Drupal::configFactory();
    }
    $this->setCacheBackend($cache_backend, 'chart');
    $this->alterInfo('charts_chart_library');
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    if ($plugin_id && $plugin_id !== 'site_default') {
      return parent::createInstance($plugin_id, $configuration);
    }

    $plugin_id = $this->getLibrary($plugin_id, $this->getDefinitions());
    return parent::createInstance($plugin_id, $configuration);
  }

}
