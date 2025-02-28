<?php

namespace Drupal\advancedqueue;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for queues.
 */
class QueueAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\advancedqueue\Entity\QueueInterface $entity */
    $admin_permission = $entity->getEntityType()->getAdminPermission();
    if ($operation === 'delete') {
      if ($entity->isLocked()) {
        return AccessResult::forbidden()->addCacheableDependency($entity);
      }
      else {
        return AccessResult::allowedIfHasPermission($account, $admin_permission)->addCacheableDependency($entity);
      }
    }
    return AccessResult::allowedIfHasPermission($account, $admin_permission);
  }

}
