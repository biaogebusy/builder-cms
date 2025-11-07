<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\ImportProcessor;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Manages import processor plugins.
 */
class ImportProcessorPluginManager extends DefaultPluginManager {

  use StringTranslationTrait;

  /**
   * Constructs an ImportProcessorPluginManager object.
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
    $subdir = 'Plugin/EntityShareClient/Processor';
    $plugin_interface = 'Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface';
    $plugin_definition_annotation_name = 'Drupal\entity_share_client\Annotation\ImportProcessor';
    parent::__construct($subdir, $namespaces, $module_handler, $plugin_interface, $plugin_definition_annotation_name);
    $this->alterInfo('entity_share_client_import_processor_info');
    $this->setCacheBackend($cache_backend, 'entity_share_client_import_processors');
  }

  /**
   * Retrieves information about the available processing stages.
   *
   * These are then used by processors in their "stages" definition to specify
   * in which stages they will run.
   *
   * @return array
   *   An associative array mapping stage identifiers to information about that
   *   stage. The information itself is an associative array with the following
   *   keys:
   *   - label: The translated label for this stage.
   */
  public function getProcessingStages() {
    return [
      ImportProcessorInterface::STAGE_PREPARE_ENTITY_DATA => [
        'label' => $this->t('Prepare entity data'),
      ],
      ImportProcessorInterface::STAGE_IS_ENTITY_IMPORTABLE => [
        'label' => $this->t('Is entity importable'),
      ],
      ImportProcessorInterface::STAGE_PREPARE_IMPORTABLE_ENTITY_DATA => [
        'label' => $this->t('Prepare importable entity data'),
      ],
      ImportProcessorInterface::STAGE_PROCESS_ENTITY => [
        'label' => $this->t('Process entity'),
      ],
      ImportProcessorInterface::STAGE_POST_ENTITY_SAVE => [
        'label' => $this->t('Post entity save'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    /** @var \Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface $instance */
    $instance = parent::createInstance($plugin_id);
    $instance->setConfiguration($configuration);
    return $instance;
  }

}
