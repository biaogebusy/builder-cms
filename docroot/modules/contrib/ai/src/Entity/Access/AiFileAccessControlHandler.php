<?php

namespace Drupal\ai\Entity\Access;

use Drupal\ai\Entity\AiFileInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for AI File entity.
 */
class AiFileAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if (!$entity instanceof AiFileInterface) {
      return AccessResult::neutral();
    }

    $is_owner = $entity->getOwnerId() && $entity->getOwnerId() === $account->id();

    switch ($operation) {
      case 'view':
        if ($account->hasPermission('view any ai file')) {
          return AccessResult::allowed()->cachePerPermissions()->addCacheableDependency($entity);
        }
        if ($account->hasPermission('view ai files') && $is_owner) {
          return AccessResult::allowed()->cachePerPermissions()->cachePerUser()->addCacheableDependency($entity);
        }
        return AccessResult::neutral()->cachePerPermissions()->cachePerUser()->addCacheableDependency($entity);

      case 'delete':
        if ($account->hasPermission('delete any ai file')) {
          return AccessResult::allowed()->cachePerPermissions()->addCacheableDependency($entity);
        }
        if ($is_owner && $account->hasPermission('delete own ai file')) {
          return AccessResult::allowed()->cachePerPermissions()->cachePerUser()->addCacheableDependency($entity);
        }
        return AccessResult::forbidden()->cachePerPermissions()->cachePerUser()->addCacheableDependency($entity);

      case 'update':
        // Not providing direct updates via UI now. Treat like view.
        if ($account->hasPermission('administer ai')) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        if ($is_owner) {
          return AccessResult::allowed()->cachePerPermissions()->cachePerUser()->addCacheableDependency($entity);
        }
        return AccessResult::neutral()->cachePerPermissions()->cachePerUser()->addCacheableDependency($entity);
    }

    return AccessResult::neutral()->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account requesting access.
   * @param array<string, mixed> $context
   *   An array of additional context values.
   * @param string|null $entity_bundle
   *   The bundle name, or NULL.
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'administer ai');
  }

}
