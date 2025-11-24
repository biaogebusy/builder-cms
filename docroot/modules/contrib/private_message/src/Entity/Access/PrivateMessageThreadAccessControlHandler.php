<?php

declare(strict_types=1);

namespace Drupal\private_message\Entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;

/**
 * Access control handler for private message thread entities.
 */
class PrivateMessageThreadAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    assert($entity instanceof PrivateMessageThreadInterface);

    if ($account->hasPermission('administer private messages')) {
      return AccessResult::allowed();
    }

    if ($account->hasPermission('use private messaging system')) {
      switch ($operation) {
        case 'view':
          if ($entity->isMember($account->id())) {
            $messages = $entity->filterUserDeletedMessages($account);
            if (count($messages)) {
              return AccessResult::allowed();
            }
          }
          break;

        case 'delete':
          // Allow to delete if we are member of this thread and if we have
          // permission to delete thread for everyone.
          if ($entity->isMember($account->id())
            && $account->hasPermission('delete private message thread for all')) {
            return AccessResult::allowed();
          }
          break;

        case 'clear_personal_history':
          // We can clear personal history only if we are member of this thread.
          if ($entity->isMember($account->id())) {
            return AccessResult::allowed();
          }
          break;
      }
    }

    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   *
   * Separate from the checkAccess because the entity does not yet exist, it
   * will be created during the 'add' process.
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'use private messaging system');
  }

}
