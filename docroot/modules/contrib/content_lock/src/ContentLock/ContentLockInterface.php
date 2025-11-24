<?php

namespace Drupal\content_lock\ContentLock;

use Drupal\Core\Entity\EntityInterface;

/**
 * Class ContentLock.
 *
 * The content lock service.
 */
interface ContentLockInterface {

  /**
   * Form operation mode disabled.
   */
  const FORM_OP_MODE_DISABLED = 0;

  /**
   * Form operation mode whitelist.
   */
  const FORM_OP_MODE_WHITELIST = 1;

  /**
   * Form operation mode blacklist.
   */
  const FORM_OP_MODE_BLACKLIST = 2;

  /**
   * Fetch the lock for an entity.
   *
   * @param int $entity_id
   *   The entity id.
   * @param string $langcode
   *   The translation language code of the entity.
   * @param string $form_op
   *   (optional) The entity form operation.
   * @param string $entity_type
   *   The entity type.
   *
   * @return object
   *   The lock for the node. FALSE, if the document is not locked.
   */
  public function fetchLock($entity_id, $langcode, $form_op = NULL, $entity_type = 'node');

  /**
   * Tell who has locked node.
   *
   * @param object $lock
   *   The lock for a node.
   * @param bool $translation_lock
   *   Defines whether the lock is on translation level or not.
   *
   * @return string
   *   String with the message.
   */
  public function displayLockOwner($lock, $translation_lock);

  /**
   * Check lock status.
   *
   * @param int $entity_id
   *   The entity id.
   * @param string $langcode
   *   The translation language code of the entity.
   * @param string $form_op
   *   The entity form operation.
   * @param int $uid
   *   The user id.
   * @param string $entity_type
   *   The entity type.
   *
   * @return bool
   *   Return TRUE OR FALSE.
   */
  public function isLockedBy($entity_id, $langcode, $form_op, $uid, $entity_type = 'node');

  /**
   * Release a locked entity.
   *
   * @param int $entity_id
   *   The entity id.
   * @param string $langcode
   *   The translation language code of the entity.
   * @param string $form_op
   *   (optional) The entity form operation.
   * @param int $uid
   *   If set, verify that a lock belongs to this user prior to release.
   * @param string $entity_type
   *   The entity type.
   */
  public function release($entity_id, $langcode, $form_op = NULL, $uid = NULL, $entity_type = 'node');

  /**
   * Release all locks set by a user.
   *
   * @param int $uid
   *   The user uid.
   */
  public function releaseAllUserLocks($uid);

  /**
   * Check if locking is verbose.
   *
   * @return bool
   *   Return true if locking is verbose.
   */
  public function verbose();

  /**
   * Try to lock a document for editing.
   *
   * @param int $entity_id
   *   The entity id.
   * @param string $langcode
   *   The translation language of the entity.
   * @param string $form_op
   *   The entity form operation.
   * @param int $uid
   *   The user id to lock the node for.
   * @param string $entity_type
   *   The entity type.
   * @param bool $quiet
   *   Suppress any normal user messages.
   * @param string $destination
   *   Destination to redirect when break. Defaults to current page.
   *
   * @return bool
   *   FALSE, if a document has already been locked by someone else.
   */
  public function locking($entity_id, $langcode, $form_op, $uid, $entity_type = 'node', $quiet = FALSE, $destination = NULL);

  /**
   * Check whether a node is configured to be protected by content_lock.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   * @param string $form_op
   *   (optional) The entity form operation.
   *
   * @return bool
   *   TRUE is entity is lockable
   */
  public function isLockable(EntityInterface $entity, $form_op = NULL);

  /**
   * Check if for this entity_type content lock over JS is enabled.
   *
   * @param string $entity_type_id
   *   The entity type id.
   *
   * @return bool
   */
  public function isJsLock($entity_type_id);

  /**
   * Builds a button class, link type form element to unlock the content.
   *
   * @param string $entity_type
   *   The entity type of the content.
   * @param int $entity_id
   *   The entity id of the content.
   * @param string $langcode
   *   The translation language code of the entity.
   * @param string $form_op
   *   The entity form operation.
   * @param string $destination
   *   The destination query parameter to build the link with.
   *
   * @return array
   *   The link form element.
   */
  public function unlockButton($entity_type, $entity_id, $langcode, $form_op, $destination);

  /**
   * Checks whether the entity type is lockable on translation level.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return bool
   *   TRUE if the entity type should be locked on translation level, FALSE if
   *   it should be locked on entity level.
   */
  public function isTranslationLockEnabled($entity_type_id);

  /**
   * Checks whether an entity type is lockable.
   *
   * @param string $entity_type_id
   *   The entity type ID to check.
   *
   * @return bool
   *   TRUE if the entity type can be locked, FALSE if not.
   */
  public function hasLockEnabled(string $entity_type_id): bool;

  /**
   * Checks whether the entity type is lockable on translation level.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return bool
   *   TRUE if the entity type should be locked on translation level, FALSE if
   *   it should be locked on entity level.
   */
  public function isFormOperationLockEnabled($entity_type_id);

}
