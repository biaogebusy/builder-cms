<?php

namespace Drupal\commerce_payment;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\entity\EntityPermissionProviderInterface;

/**
 * Provides permissions for payment methods.
 */
class PaymentMethodPermissionProvider implements EntityPermissionProviderInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildPermissions(EntityTypeInterface $entity_type) {
    $entity_type_id = $entity_type->id();
    $plural_label = $entity_type->getPluralLabel();

    $admin_permission = $entity_type->getAdminPermission() ?: "administer {$entity_type_id}";
    $provider = $entity_type->getProvider();
    $permissions[$admin_permission] = [
      'title' => (string) $this->t('Administer @type', ['@type' => $plural_label]),
      'restrict access' => TRUE,
      'provider' => $provider,
    ];
    $permissions["view any {$entity_type_id}"] = [
      'title' => (string) $this->t('View any payment method'),
      'restrict access' => TRUE,
      'provider' => $provider,
    ];
    $permissions["update any {$entity_type_id}"] = [
      'title' => (string) $this->t('Update any payment method'),
      'restrict access' => TRUE,
      'provider' => $provider,
    ];
    $permissions["delete any {$entity_type_id}"] = [
      'title' => (string) $this->t('Delete any payment method'),
      'restrict access' => TRUE,
      'provider' => $provider,
    ];
    $permissions["manage own {$entity_type_id}"] = [
      'title' => (string) $this->t('Manage own @type', [
        '@type' => $plural_label,
      ]),
      'provider' => $provider,
    ];

    return $permissions;
  }

}
