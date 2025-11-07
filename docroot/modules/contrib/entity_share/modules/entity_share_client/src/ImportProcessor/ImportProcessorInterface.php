<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\ImportProcessor;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_share_client\RuntimeImportContext;

/**
 * An interface for Import processor plugins.
 */
interface ImportProcessorInterface extends ConfigurableInterface {

  /**
   * Processing stage: prepare entity data.
   *
   * @see \Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface::prepareEntityData()
   */
  const STAGE_PREPARE_ENTITY_DATA = 'prepare_entity_data';

  /**
   * Processing stage: is entity importable.
   *
   * @see \Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface::isEntityImportable()
   */
  const STAGE_IS_ENTITY_IMPORTABLE = 'is_entity_importable';

  /**
   * Processing stage: prepare importable entity data.
   *
   * @see \Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface::prepareImportableEntityData()
   */
  const STAGE_PREPARE_IMPORTABLE_ENTITY_DATA = 'prepare_importable_entity_data';

  /**
   * Processing stage: process entity.
   *
   * @see \Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface::processEntity()
   */
  const STAGE_PROCESS_ENTITY = 'process_entity';

  /**
   * Processing stage: post entity save.
   *
   * @see \Drupal\entity_share_client\ImportProcessor\ImportProcessorInterface::postEntitySave()
   */
  const STAGE_POST_ENTITY_SAVE = 'post_entity_save';

  /**
   * Returns the label for use on the administration pages.
   *
   * @return string
   *   The administration label.
   */
  public function label();

  /**
   * Returns the plugin's description.
   *
   * @return string
   *   A string describing the plugin. Might contain HTML and should be already
   *   sanitized for output.
   */
  public function getDescription();

  /**
   * Checks whether this processor implements a particular stage.
   *
   * @param string $stage
   *   The stage to check: one of the self::STAGE_* constants.
   *
   * @return bool
   *   TRUE if the processor runs on this particular stage; FALSE otherwise.
   */
  public function supportsStage($stage);

  /**
   * Returns the weight for a specific processing stage.
   *
   * @param string $stage
   *   The stage whose weight should be returned.
   *
   * @return int
   *   The default weight for the given stage.
   *
   * @see \Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginManager::getProcessingStages()
   */
  public function getWeight($stage);

  /**
   * Sets the weight for a specific processing stage.
   *
   * @param string $stage
   *   The stage whose weight should be set.
   * @param int $weight
   *   The weight for the given stage.
   *
   * @return $this
   *
   * @see \Drupal\entity_share_client\ImportProcessor\ImportProcessorPluginManager::getProcessingStages()
   */
  public function setWeight($stage, $weight);

  /**
   * Determines whether this processor should always be enabled.
   *
   * @return bool
   *   TRUE if this processor should be forced enabled; FALSE otherwise.
   */
  public function isLocked();

  /**
   * Method called on STAGE_PREPARE_ENTITY_DATA.
   *
   * If the plugin reacts to this stage.
   *
   * @param \Drupal\entity_share_client\RuntimeImportContext $runtime_import_context
   *   The import context.
   * @param array $entity_json_data
   *   The entity JSON data.
   */
  public function prepareEntityData(RuntimeImportContext $runtime_import_context, array &$entity_json_data);

  /**
   * Method called on STAGE_IS_ENTITY_IMPORTABLE.
   *
   * If the plugin reacts to this stage.
   *
   * @param \Drupal\entity_share_client\RuntimeImportContext $runtime_import_context
   *   The import context.
   * @param array $entity_json_data
   *   The entity JSON data.
   *
   * @return bool
   *   TRUE if the entity is importable. FALSE otherwise.
   */
  public function isEntityImportable(RuntimeImportContext $runtime_import_context, array $entity_json_data);

  /**
   * Method called on STAGE_PREPARE_IMPORTABLE_ENTITY_DATA.
   *
   * If the plugin reacts to this stage.
   *
   * @param \Drupal\entity_share_client\RuntimeImportContext $runtime_import_context
   *   The import context.
   * @param array $entity_json_data
   *   The entity JSON data.
   */
  public function prepareImportableEntityData(RuntimeImportContext $runtime_import_context, array &$entity_json_data);

  /**
   * Method called on STAGE_PROCESS_ENTITY.
   *
   * If the plugin reacts to this stage.
   *
   * @param \Drupal\entity_share_client\RuntimeImportContext $runtime_import_context
   *   The import context.
   * @param \Drupal\Core\Entity\ContentEntityInterface $processed_entity
   *   The entity being processed.
   * @param array $entity_json_data
   *   The entity JSON data.
   */
  public function processEntity(RuntimeImportContext $runtime_import_context, ContentEntityInterface $processed_entity, array $entity_json_data);

  /**
   * Method called on STAGE_POST_ENTITY_SAVE.
   *
   * If the plugin reacts to this stage.
   *
   * @param \Drupal\entity_share_client\RuntimeImportContext $runtime_import_context
   *   The import context.
   * @param \Drupal\Core\Entity\ContentEntityInterface $processed_entity
   *   The entity being processed.
   */
  public function postEntitySave(RuntimeImportContext $runtime_import_context, ContentEntityInterface $processed_entity);

}
