<?php

declare(strict_types=1);

namespace Drupal\private_message\Entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\PrivateMessageBanInterface;

/**
 * Access controller for the Private Message Ban entities.
 *
 * @see \Drupal\private_message\Entity\PrivateMessageBan
 */
class PrivateMessageBanAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    assert($entity instanceof PrivateMessageBanInterface);

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view private message ban entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit private message ban entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete private message ban entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'add private message ban entities');
  }

}
