<?php

namespace Drupal\eck;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the EckEntity entity.
 *
 * @ingroup eck
 *
 * @see \Drupal\eck\Entity\EckEntity.
 */
class EckEntityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * Determines if the given account is allowed to bypass access control.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account.
   *
   * @return bool
   *   Can the user bypass the access check?
   */
  private function canBypassAccessCheck(?AccountInterface $account = NULL) {
    $account = $this->prepareUser($account);
    return $account->hasPermission('bypass eck entity access');
  }

  /**
   * Generates an AccessResult.
   *
   * @param bool $return_as_object
   *   Should a bool or AccessResult object be returned?
   *
   * @return \Drupal\Core\Access\AccessResult|bool
   *   The created access result.
   */
  private function getBypassAccessResult($return_as_object) {
    $result = AccessResult::allowed()->cachePerPermissions();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity, $operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($this->canBypassAccessCheck($account)) {
      return $this->getBypassAccessResult($return_as_object);
    }
    else {
      return parent::access($entity, $operation, $account, $return_as_object);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createAccess($entity_bundle = NULL, ?AccountInterface $account = NULL, array $context = [], $return_as_object = FALSE) {
    if ($this->canBypassAccessCheck($account)) {
      return $this->getBypassAccessResult($return_as_object);
    }
    else {
      return parent::createAccess($entity_bundle, $account, $context, $return_as_object);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $operation = ($operation == 'update') ? 'edit' : $operation;
    $permissions[] = $operation . ' any ' . $entity->getEntityTypeId() . ' entities';
    if ($entity->getOwnerId() == $account->id()) {
      $permissions[] = $operation . ' own ' . $entity->getEntityTypeId() . ' entities';
    }
    $default_entity_access = AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');

    // Unpublished entities require at least one of the default permissions:
    // 'views own' or 'view any' and 'view unpublished'.
    if ($operation == 'view' && !$entity->isPublished()) {
      return AccessResult::allowedIf($default_entity_access && $account->hasPermission('view unpublished eck entities'));
    }
    return $default_entity_access;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $permissions = [
      'create ' . $this->entityTypeId . ' entities',
    ];

    if (!empty($entity_bundle)) {
      $permissions[] = 'create ' . $this->entityTypeId . ' entities of bundle ' . $entity_bundle;
    }
    return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR')
      ->cachePerPermissions();
  }

}
