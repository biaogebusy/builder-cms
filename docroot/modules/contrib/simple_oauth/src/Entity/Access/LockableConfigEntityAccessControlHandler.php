<?php

namespace Drupal\simple_oauth\Entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Access Token type entity.
 *
 * @see \Drupal\simple_oauth\Entity\Oauth2TokenType.
 */
class LockableConfigEntityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation == 'view') {
      // Allow viewing the configuration entity.
      return AccessResult::allowed();
    }
    if ($entity->isLocked()) {
      return AccessResult::forbidden();
    }
    return parent::checkAccess($entity, $operation, $account);
  }

}
