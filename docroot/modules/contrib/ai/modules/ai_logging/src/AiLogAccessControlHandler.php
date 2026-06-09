<?php

namespace Drupal\ai_logging;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the AI Log entity.
 */
class AiLogAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation === 'view') {
      // Check if the user has the 'view ai log' permission.
      if ($account->hasPermission('view ai log')) {
        return AccessResult::allowed();
      }
      // Fallback to default access denied if no permission.
      return AccessResult::forbidden();
    }

    // Any other operation, leave the defaults.
    return parent::checkAccess($entity, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // Define who can create AI Log entities. We'll reuse the
    // 'administer ai log' permission here.
    return AccessResult::allowedIfHasPermission($account, 'administer ai log');
  }

}
