<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\entity_share_client\Entity\ImportConfigInterface;

/**
 * Import config manipulator interface methods.
 */
interface ImportConfigManipulatorInterface {

  /**
   * Retrieves this import config's processors.
   *
   * @param \Drupal\entity_share_client\Entity\ImportConfigInterface $import_config
   *   The import config.
   *
   * @return \Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface[]
   *   An array of all enabled processors for this import config.
   */
  public function getImportProcessors(ImportConfigInterface $import_config);

  /**
   * Retrieves a specific processor plugin for this import config.
   *
   * @param \Drupal\entity_share_client\Entity\ImportConfigInterface $import_config
   *   The import config.
   * @param string $processor_id
   *   The ID of the processor plugin to return.
   *
   * @return \Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface
   *   The processor plugin with the given ID.
   *
   * @throws \Exception
   *   Thrown if the specified processor isn't enabled for this import config,
   *   or couldn't be loaded.
   */
  public function getImportProcessor(ImportConfigInterface $import_config, $processor_id);

  /**
   * Loads this import config's processors for a specific stage.
   *
   * @param \Drupal\entity_share_client\Entity\ImportConfigInterface $import_config
   *   The import config.
   * @param array[] $overrides
   *   (optional) Overrides to apply to the import config's processors, keyed by
   *   processor IDs with their respective overridden settings as values.
   *
   * @return \Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface[][]
   *   An array of all enabled processors that support the given stage for each
   *   stage, ordered by the weight for that stage.
   */
  public function getImportProcessorsByStages(ImportConfigInterface $import_config, array $overrides = []);

  /**
   * Loads this import config's processors for a specific stage.
   *
   * @param \Drupal\entity_share_client\Entity\ImportConfigInterface $import_config
   *   The import config.
   * @param string $stage
   *   The stage for which to return the processors. One of the
   *   \Drupal\entity_share_client\ImportProcessor\
   *   ImportProcessorInterface::STAGE_* constants.
   * @param array[] $overrides
   *   (optional) Overrides to apply to the import config's processors, keyed by
   *   processor IDs with their respective overridden settings as values.
   *
   * @return \Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface[]
   *   An array of all enabled processors that support the given stage, ordered
   *   by the weight for that stage.
   */
  public function getImportProcessorsByStage(ImportConfigInterface $import_config, $stage, array $overrides = []);

}
