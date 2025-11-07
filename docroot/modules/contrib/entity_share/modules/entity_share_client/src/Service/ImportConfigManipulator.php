<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\entity_share_client\Entity\ImportConfigInterface;
use Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginManager;

/**
 * Class ImportConfigManipulator.
 *
 * Instantiate import processor plugins from an import config entity type.
 *
 * @package Drupal\entity_share_client\Service
 */
class ImportConfigManipulator implements ImportConfigManipulatorInterface {

  /**
   * The import processor plugin manager.
   *
   * @var \Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginManager
   */
  protected $importProcessorPluginManager;

  /**
   * Constructs an ImportConfigManipulator object.
   *
   * @param \Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginManager $import_processor_plugin_manager
   *   The import processor plugin manager.
   */
  public function __construct(ImportProcessorPluginManager $import_processor_plugin_manager) {
    $this->importProcessorPluginManager = $import_processor_plugin_manager;
  }

  /**
   * Creates multiple plugin objects for the given import config.
   *
   * @param \Drupal\entity_share_client\Entity\ImportConfigInterface $import_config
   *   The import config for which to create the plugins.
   * @param string[]|null $plugin_ids
   *   (optional) The IDs of the plugins to create, or NULL to create instances
   *   for all known plugins of this type.
   * @param array $configurations
   *   (optional) The configurations to set for the plugins, keyed by plugin ID.
   *   Missing configurations are either taken from the index's stored settings,
   *   if they are present there, or default to an empty array.
   *
   * @return \Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface[]
   *   The created plugin objects.
   *
   * @throws \Exception
   *   Thrown if an unknown $type or plugin ID is given.
   */
  protected function createImportProcessorPlugins(ImportConfigInterface $import_config, array $plugin_ids = NULL, array $configurations = []) {
    if ($plugin_ids === NULL) {
      $plugin_ids = array_keys($this->importProcessorPluginManager->getDefinitions());
    }

    $plugins = [];
    $import_config_settings = $import_config->get('import_processor_settings');
    foreach ($plugin_ids as $plugin_id) {
      $configuration = [];
      if (isset($configurations[$plugin_id])) {
        $configuration = $configurations[$plugin_id];
      }
      elseif (isset($import_config_settings[$plugin_id])) {
        $configuration = $import_config_settings[$plugin_id];
      }

      try {
        $plugins[$plugin_id] = $this->importProcessorPluginManager->createInstance($plugin_id, $configuration);
      }
      catch (PluginException $exception) {
        throw new \Exception("Unknown import processor plugin with ID '$plugin_id'");
      }
    }

    return $plugins;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportProcessors(ImportConfigInterface $import_config) {
    // Filter the processors to only include those that are enabled (or locked).
    // We should only reach this point in the code once, at the first call after
    // the index is loaded.
    $returned_processors = [];
    $processors = $this->createImportProcessorPlugins($import_config);
    foreach ($processors as $processor_id => $processor) {
      if (isset($import_config->get('import_processor_settings')[$processor_id]) || $processor->isLocked()) {
        $returned_processors[$processor_id] = $processor;
      }
    }

    return $returned_processors;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportProcessor(ImportConfigInterface $import_config, $processor_id) {
    $processors = $this->getImportProcessors($import_config);

    if (empty($processors[$processor_id])) {
      $import_config_label = $import_config->label();
      throw new \Exception("The import processor with ID '$processor_id' could not be retrieved for import config '$import_config_label'.");
    }

    return $processors[$processor_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getImportProcessorsByStages(ImportConfigInterface $import_config, array $overrides = []) {
    $return_processors = [];
    $stages = $this->importProcessorPluginManager->getProcessingStages();
    foreach (array_keys($stages) as $stage) {
      $return_processors[$stage] = $this->getImportProcessorsByStage($import_config, $stage, $overrides);
    }

    return $return_processors;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportProcessorsByStage(ImportConfigInterface $import_config, $stage, array $overrides = []) {
    // Get a list of all import processors which support this stage, along with
    // their weights.
    $processors = $this->getImportProcessors($import_config);
    $processor_weights = [];
    foreach ($processors as $name => $processor) {
      if ($processor->supportsStage($stage)) {
        $processor_weights[$name] = $processor->getWeight($stage);
      }
    }

    // Apply any overrides that were passed by the caller.
    foreach ($overrides as $name => $config) {
      $processor = $this->importProcessorPluginManager->createInstance($name, $config);
      if ($processor->supportsStage($stage)) {
        $processors[$name] = $processor;
        $processor_weights[$name] = $processor->getWeight($stage);
      }
      else {
        // In rare cases, the override might change whether or not the import
        // processor supports the given stage. So, to make sure, unset the
        // weight in case it was set before.
        unset($processor_weights[$name]);
      }
    }

    // Sort requested import processors by weight.
    asort($processor_weights);

    $return_processors = [];
    foreach (array_keys($processor_weights) as $name) {
      $return_processors[$name] = $processors[$name];
    }

    return $return_processors;
  }

}
