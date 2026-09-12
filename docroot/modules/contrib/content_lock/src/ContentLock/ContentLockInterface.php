<?php

namespace Drupal\content_lock\ContentLock;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

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
   * Form operation mode allowlist.
   */
  const FORM_OP_MODE_ALLOWLIST = 1;

  /**
   * Form operation mode denylist.
   */
  const FORM_OP_MODE_DENYLIST = 2;

  /**
   * Fetch the lock for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to lock.
   * @param string|null $form_op
   *   (optional) The entity form operation.
   * @param bool $include_stale_locks
   *   (optional) Whether to include stale locks. Defaults to FALSE.
   *
   * @return object|false
   *   The lock for the node. FALSE, if the document is not locked.
   */
  public function fetchLock(EntityInterface $entity, ?string $form_op = NULL, bool $include_stale_locks = FALSE): object|false;

  /**
   * Tell who has locked node.
   *
   * @param object $lock
   *   The lock for a node.
   * @param bool $translation_lock
   *   Defines whether the lock is on translation level or not.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   String with the message.
   */
  public function displayLockOwner(object $lock, bool $translation_lock): string|TranslatableMarkup;

  /**
   * Check lock status.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $form_op
   *   The entity form operation.
   * @param int $uid
   *   The user id.
   *
   * @return bool
   *   Return TRUE OR FALSE.
   */
  public function isLockedBy(EntityInterface $entity, string $form_op, int $uid): bool;

  /**
   * Release a locked entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string|null $form_op
   *   (optional) The entity form operation.
   * @param int|null $uid
   *   (optional) If set, verify that a lock belongs to this user prior to
   *   release.
   */
  public function release(EntityInterface $entity, ?string $form_op = NULL, ?int $uid = NULL): void;

  /**
   * Release all locks set by a user.
   *
   * @param int $uid
   *   The user uid.
   */
  public function releaseAllUserLocks(int $uid): void;

  /**
   * Release all expired locks.
   *
   * @return int
   *   The number of lock released.
   */
  public function releaseExpiredLocks(): int;

  /**
   * Check if locking is verbose.
   *
   * @return bool
   *   Return true if locking is verbose.
   */
  public function verbose(): bool;

  /**
   * Try to lock a document for editing.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $form_op
   *   The entity form operation.
   * @param int $uid
   *   The user id to lock the node for.
   * @param bool $quiet
   *   (optional) Suppress any normal user messages.
   * @param string|null $destination
   *   (optional) Destination to redirect when breaking the lock. Defaults to
   *   current page.
   * @param array|null $messages
   *   (optional) If an empty array is passed in as reference messages will be
   *   added to display to the user. It is up to the caller to display the
   *   messages.
   *
   * @return bool
   *   FALSE, if a document has already been locked by someone else.
   */
  public function locking(EntityInterface $entity, string $form_op, int $uid, bool $quiet = FALSE, ?string $destination = NULL, ?array &$messages = NULL): bool;

  /**
   * Check whether a node is configured to be protected by content_lock.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   * @param string|null $form_op
   *   (optional) The entity form operation.
   *
   * @return bool
   *   TRUE is entity is lockable
   */
  public function isLockable(EntityInterface $entity, ?string $form_op = NULL): bool;

  /**
   * Builds a button class, link type form element to unlock the content.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string|null $form_op
   *   The entity form operation.
   * @param string|null $destination
   *   The destination query parameter to build the link with.
   *
   * @return array
   *   The link form element.
   */
  public function unlockButton(EntityInterface $entity, ?string $form_op, ?string $destination): array;

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
  public function isTranslationLockEnabled(string $entity_type_id): bool;

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
  public function isFormOperationLockEnabled(string $entity_type_id): bool;

}
