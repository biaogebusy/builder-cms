<?php

namespace Drupal\recently_read;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Interface RecentlyReadServiceInterface.
 *
 * @package Drupal\recently_read
 */
interface RecentlyReadServiceInterface {

  /**
   * Custom function to insert or update an entry for recently read.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The viewed entity.
   * @param \Drupal\Core\Session\AccountInterface|null $user
   *   The user who read the entity. If NULL then the current user will be used.
   */
  public function insertEntity(EntityInterface $entity, AccountInterface $user = NULL);

  /**
   * Delete records from DB.
   *
   * @param array $records
   *   Number of records to delete.
   */
  public function deleteRecords(array $records);

  /**
   * Get all records in DB for specified user/anonymous.
   *
   * @param int $user_id
   *   User id.
   *
   * @return array|int
   *   Returns an array of record id's.
   */
  public function getRecords($user_id);

  /**
   * Delete all "Recently Read" IDs for the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity which might has "Recently Read" records.
   * @param \Drupal\Core\Session\AccountInterface|null $user
   *   The user who read the entity.
   */
  public function deleteEntityRecords(EntityInterface $entity, AccountInterface $user = NULL);

}
