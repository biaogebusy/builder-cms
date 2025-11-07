<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for defining Entity import status entities.
 */
interface EntityImportStatusInterface extends ContentEntityInterface {

  /**
   * Denotes the default entity import policy.
   */
  const IMPORT_POLICY_DEFAULT = 'default';

  /**
   * Returns the last import time.
   *
   * @return int
   *   The timestamp of entity's last import.
   */
  public function getLastImport();

  /**
   * Updates the last import time.
   *
   * @param int $timestamp
   *   The timestamp of import.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setLastImport($timestamp);

  /**
   * Returns the import policy of entity.
   *
   * @return string
   *   The import policy.
   */
  public function getPolicy();

  /**
   * Sets the import policy of entity.
   *
   * @param string $policy
   *   The import policy.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setPolicy($policy);

}
