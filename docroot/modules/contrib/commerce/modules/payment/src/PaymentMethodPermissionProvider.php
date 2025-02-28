<?php

namespace Drupal\commerce_payment;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity\EntityPermissionProviderBase;

/**
 * Provides permissions for payment methods.
 */
class PaymentMethodPermissionProvider extends EntityPermissionProviderBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildPermissions(EntityTypeInterface $entity_type) {
    $entity_type_id = $entity_type->id();
    $plural_label = $entity_type->getPluralLabel();

    $admin_permission = $entity_type->getAdminPermission() ?: "administer {$entity_type_id}";
    $permissions[$admin_permission] = [
      'title' => $this->t('Administer @type', ['@type' => $plural_label]),
      'restrict access' => TRUE,
    ];
    $permissions["view any {$entity_type_id}"] = [
      'title' => $this->t('View any payment method'),
      'restrict access' => TRUE,
    ];
    $permissions["update any {$entity_type_id}"] = [
      'title' => $this->t('Update any payment method'),
      'restrict access' => TRUE,
    ];
    $permissions["delete any {$entity_type_id}"] = [
      'title' => $this->t('Delete any payment method'),
      'restrict access' => TRUE,
    ];
    $permissions["manage own {$entity_type_id}"] = [
      'title' => $this->t('Manage own @type', [
        '@type' => $plural_label,
      ]),
    ];

    return $this->processPermissions($permissions, $entity_type);
  }

}
