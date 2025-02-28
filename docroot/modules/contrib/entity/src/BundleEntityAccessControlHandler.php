<?php

namespace Drupal\entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler as CoreEntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Controls access to bundle entities.
 *
 * Allows the bundle entity label to be viewed if the account has
 * access to view entities of that bundle.
 */
class BundleEntityAccessControlHandler extends CoreEntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected $viewLabelOperation = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation === 'view label') {
      $bundle = $entity->id();
      $entity_type_id = $this->entityType->getBundleOf();
      $permissions = [
        $this->entityType->getAdminPermission() ?: "administer $entity_type_id",
        // View permissions provided by EntityPermissionProvider.
        "view $entity_type_id",
        "view $bundle $entity_type_id",
        // View permissions provided by UncacheableEntityPermissionProvider.
        "view own $entity_type_id",
        "view any $entity_type_id",
        "view own $bundle $entity_type_id",
        "view any $bundle $entity_type_id",
      ];

      return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
    }
    else {
      return parent::checkAccess($entity, $operation, $account);
    }
  }

}
