<?php

namespace Drupal\simple_oauth\Entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the OAuth2 scope entity.
 *
 * @see \Drupal\simple_oauth\Entity\Oauth2Scope.
 */
class Oauth2ScopeAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($admin_permission = $this->entityType->getAdminPermission()) {
      return AccessResult::allowedIfHasPermission($account, $admin_permission);
    }

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view oauth2 scopes');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit oauth2 scopes');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete oauth2 scopes');

      default:
        return parent::checkAccess($entity, $operation, $account);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add oauth2 scopes');
  }

}
