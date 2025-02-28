<?php

namespace Drupal\charts\EventSubscriber;

use Drupal\charts\ChartManager;
use Drupal\charts\DependenciesCalculatorTrait;
use Drupal\charts\TypeManager;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Config\StorageTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Ensure charts settings are calculated when configurations are imported.
 */
class ConfigImportSubscriber implements EventSubscriberInterface {

  use DependenciesCalculatorTrait;

  /**
   * Constructs a ConfigImportSubscriber instance.
   *
   * @param \Drupal\charts\ChartManager $chart_manager
   *   The chart library plugin manager.
   * @param \Drupal\charts\TypeManager $chart_type_manager
   *   The chart type plugin manager.
   */
  public function __construct(ChartManager $chart_manager, TypeManager $chart_type_manager) {
    $this->chartPluginManager = $chart_manager;
    $this->chartTypePluginManager = $chart_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::STORAGE_TRANSFORM_IMPORT => ['onImportTransform'],
      // There is no specific reason for choosing 50 beside it should be
      // executed before \Drupal\Core\EventSubscriber::onConfigImporterImport()
      // set at 40.
      ConfigEvents::IMPORT => ['onConfigImporterImport', 50],
    ];
  }

  /**
   * Ensure the config dependencies are calculated for charts settings.
   *
   * @param \Drupal\Core\Config\ConfigImporterEvent $event
   *   The event to process.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onConfigImporterImport(ConfigImporterEvent $event) {
    $config_importer = $event->getConfigImporter();
    $storage_comparer = $config_importer->getStorageComparer();
    $source_storage = $storage_comparer->getSourceStorage();
    $charts_config = $source_storage->read('charts.settings');
    if ($settings = $charts_config['charts_default_settings'] ?? []) {
      $target_storage = $storage_comparer->getTargetStorage();
      $library = $settings['library'] ?? '';
      $type = $settings['type'] ?? '';
      $charts_config['dependencies'] = $this->calculateDependencies($library, $type);
      $target_storage->write('charts.settings', $charts_config);
    }
  }

  /**
   * Acts when the storage is transformed for import.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The config storage transform event.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onImportTransform(StorageTransformEvent $event) {
    $storage = $event->getStorage();
    if ($charts_config = $storage->read('charts.settings')) {
      $settings = $charts_config['charts_default_settings'];
      $library = $settings['library'] ?? '';
      $type = $settings['type'] ?? '';
      $charts_config['dependencies'] = $this->calculateDependencies($library, $type);
      $storage->write('charts.settings', $charts_config);
    }
  }

}
