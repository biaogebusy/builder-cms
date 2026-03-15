<?php

namespace Drupal\social_auth\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for the social_auth type entity.
 */
class SocialAuthAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\user\UserInterface $entity*/

    if ($operation === 'view label') {
      return AccessResult::allowed();
    }

    if ($operation === 'delete') {

      // 'administer users' or 'administer social auth profiles'
      // can delete all user profiles.
      if ($account->hasPermission('administer users') ||
        $account->hasPermission($this->entityType->getAdminPermission())
      ) {
        return AccessResult::allowed()
          ->cachePerPermissions();
      }

      // Users with 'delete own social auth profile' permission
      // can delete social auths.
      return AccessResult::allowedIfHasPermission($account, 'delete own social auth profile')
        ->andIf(AccessResult::allowedIf($account
          ->id() === $entity
          ->user_id->target_id)
          ->cachePerUser());

    }
    return parent::checkAccess($entity, $operation, $account);
  }

}
