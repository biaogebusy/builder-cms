<?php

namespace Drupal\commerce_order;

use Drupal\commerce\CommerceBundleAccessControlHandler;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Controls access to order item type entities.
 *
 * Allows viewing the label of an order item type if its order items are
 * viewable.
 */
class OrderItemTypeAccessControlHandler extends CommerceBundleAccessControlHandler {

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
      $permissions = [
        'administer commerce_order',
        'access commerce_order overview',
        "manage $bundle commerce_order_item",
      ];

      return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
