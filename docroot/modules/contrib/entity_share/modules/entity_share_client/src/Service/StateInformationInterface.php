<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Remote manager interface methods.
 */
interface StateInformationInterface {

  /**
   * The info id in the case of an undefined state.
   */
  const INFO_ID_UNDEFINED = 'undefined';

  /**
   * The info id in the case of an unknown entity type.
   */
  const INFO_ID_UNKNOWN = 'unknown';

  /**
   * The info id in the case of a new entity.
   */
  const INFO_ID_NEW = 'new';

  /**
   * The info id in the case of a new entity translation.
   */
  const INFO_ID_NEW_TRANSLATION = 'new_translation';

  /**
   * The info id in the case of a changed entity or translation.
   */
  const INFO_ID_CHANGED = 'changed';

  /**
   * The info id in the case of a synchronized entity or translation.
   */
  const INFO_ID_SYNCHRONIZED = 'synchronized';

  /**
   * Check if an entity already exists or not and get status info.
   *
   * Default implementation is to compare revision timestamp.
   *
   * @param array $data
   *   The data of a single entity from the JSON:API payload.
   *
   * @return array
   *   Returns an array of info:
   *     - label: the label to display.
   *     - class: to add a class on a row.
   *     - info_id: an identifier of the status info.
   *     - local_entity_link: the link of the local entity if it exists.
   *     - local_revision_id: the revision ID of the local entity if it exists.
   *     - policy: the policy label or ID if the policy can not be found.
   */
  public function getStatusInfo(array $data);

  /**
   * {@inheritdoc}
   *
   * @param string $status_info_id
   *   An identifier of the status info (the value of 'INFO_ID_...' constant).
   *
   * @return array
   *   Keyed by status ID, values containing:
   *     - label,
   *     - CSS class suffix.
   */
  public function getStatusDefinition(string $status_info_id);

  /**
   * Creates a dedicated "Entity import status" entity for imported entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being imported.
   * @param array $parameters
   *   Other data from the import context, with valid keys:
   *     - remote_website.
   *     - channel_id.
   *     - policy.
   *
   * @return \Drupal\entity_share_client\Entity\EntityImportStatusInterface|bool
   *   The newly created "Entity import status" entity or FALSE on failure.
   */
  public function createImportStatusOfEntity(ContentEntityInterface $entity, array $parameters);

  /**
   * Gets the dedicated "Entity import status" entity for given parameters.
   *
   * @param string $uuid
   *   UUID.
   * @param string $entity_type_id
   *   Entity type identifier.
   * @param string|null $langcode
   *   Language code.
   *
   * @return \Drupal\entity_share_client\Entity\EntityImportStatusInterface|bool
   *   The "Entity import status" entity or FALSE if none found.
   */
  public function getImportStatusByParameters(string $uuid, string $entity_type_id, string $langcode = NULL);

  /**
   * Gets the dedicated "Entity import status" entity for imported entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being imported.
   *
   * @return \Drupal\entity_share_client\Entity\EntityImportStatusInterface|bool
   *   The "Entity import status" entity or FALSE if none found.
   */
  public function getImportStatusOfEntity(ContentEntityInterface $entity);

  /**
   * Deletes the "Entity import status" entity of an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity which had been imported.
   * @param string|null $langcode
   *   Optional language code, used when deleting only specific translations.
   */
  public function deleteImportStatusOfEntity(EntityInterface $entity, string $langcode = NULL);

}
