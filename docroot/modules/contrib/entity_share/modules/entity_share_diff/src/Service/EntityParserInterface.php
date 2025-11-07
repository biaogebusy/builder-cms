<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_share_client\Entity\RemoteInterface;

/**
 * Entity parser interface methods.
 */
interface EntityParserInterface {

  /**
   * Prepares entity loaded from local database.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   A Drupal content entity.
   */
  public function prepareLocalEntity(ContentEntityInterface $entity);

  /**
   * Prepares entity loaded from remote by JSON:API.
   *
   * @param array $remote_data
   *   JSON:API data of a single entity.
   * @param \Drupal\entity_share_client\Entity\RemoteInterface $remote
   *   Entity share Remote config entity.
   */
  public function prepareRemoteEntity(array $remote_data, RemoteInterface $remote);

  /**
   * Determines if the entity has already been processed in the Diff.
   */
  public function validateNeedToProcess(string $uuid, bool $remote);

  /**
   * Gets 'changed' timestamp of remote entity, if available.
   *
   * @param array $remote_data
   *   JSON:API data of a single entity.
   */
  public function getRemoteChangedTime(array $remote_data);

}
