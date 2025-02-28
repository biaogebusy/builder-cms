<?php

namespace Drupal\commerce_payment;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsUpdatingStoredPaymentMethodsInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for payment methods.
 */
class PaymentMethodAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $entity */
    if ($operation == 'update') {
      $payment_gateway = $entity->getPaymentGateway();
      // Deny access if the gateway is missing or doesn't support updates.
      if (!$payment_gateway) {
        return AccessResult::forbidden()->addCacheableDependency($entity);
      }
      if (!($payment_gateway->getPlugin() instanceof SupportsUpdatingStoredPaymentMethodsInterface)) {
        return AccessResult::forbidden()->addCacheableDependency($entity);
      }
    }

    $any_result = AccessResult::allowedIfHasPermissions($account, [
      "$operation any commerce_payment_method",
      $this->entityType->getAdminPermission(),
    ], 'OR');

    if ($any_result->isAllowed()) {
      return $any_result;
    }

    if ($account->id() == $entity->getOwnerId()) {
      $own_result = AccessResult::allowedIfHasPermission($account, 'manage own commerce_payment_method')
        ->addCacheableDependency($entity);
    }
    else {
      $own_result = AccessResult::neutral()->cachePerPermissions();
    }

    return $own_result->cachePerUser();
  }

  /**
   * {@inheritdoc}
   */
  public function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, [
      $this->entityType->getAdminPermission(),
      'manage own commerce_payment_method',
    ], 'OR');
  }

}
