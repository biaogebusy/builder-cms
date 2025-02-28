<?php

namespace Drupal\commerce_payment;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Controls access to payment gateway entities.
 *
 * Allows the payment gateway entity label to be viewed if a user has
 * administrative permission or can manage own payment methods.
 */
class PaymentGatewayAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected $viewLabelOperation = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation === 'view label') {
      return AccessResult::allowedIfHasPermissions($account, [
        $this->entityType->getAdminPermission(),
        'manage own commerce_payment_method',
      ], 'OR');
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
