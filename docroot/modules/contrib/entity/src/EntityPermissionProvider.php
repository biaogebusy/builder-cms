<?php

namespace Drupal\entity;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Provides generic entity permissions.
 *
 * Intended for content entity types, since config entity types usually rely
 * on a single "administer" permission.
 *
 * Provided permissions:
 * - The declared "admin_permission" of the entity type (or
 *   "administer $entity_type" if the entity type does not declare an
 *   administrative permission)
 * - access $entity_type overview
 * - view own unpublished $entity_type
 * - view ($bundle) $entity_type
 * - update (own|any) ($bundle) $entity_type
 * - duplicate (own|any) ($bundle) $entity_type
 * - delete (own|any) ($bundle) $entity_type
 * - create $bundle $entity_type
 *
 * Does not provide "view own ($bundle) $entity_type" permissions, because
 * they require caching pages per user. Please use
 * \Drupal\entity\UncacheableEntityPermissionProvider if those permissions
 * are necessary.
 *
 * Example annotation:
 * @code
 *  handlers = {
 *    "access" = "Drupal\entity\EntityAccessControlHandler",
 *    "permission_provider" = "Drupal\entity\EntityPermissionProvider",
 *  }
 * @endcode
 *
 * @see \Drupal\entity\EntityAccessControlHandler
 * @see \Drupal\entity\EntityPermissions
 */
class EntityPermissionProvider extends EntityPermissionProviderBase {

  /**
   * Builds permissions for the entity_type granularity.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array
   *   The permissions.
   */
  protected function buildEntityTypePermissions(EntityTypeInterface $entity_type) {
    $permissions = parent::buildEntityTypePermissions($entity_type);
    $entity_type_id = $entity_type->id();
    $plural_label = $entity_type->getPluralLabel();

    $permissions["view {$entity_type_id}"] = [
      'title' => $this->t('View @type', [
        '@type' => $plural_label,
      ]),
    ];

    return $permissions;
  }

  /**
   * Builds permissions for the bundle granularity.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array
   *   The permissions.
   */
  protected function buildBundlePermissions(EntityTypeInterface $entity_type) {
    $permissions = parent::buildBundlePermissions($entity_type);
    $entity_type_id = $entity_type->id();
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    $plural_label = $entity_type->getPluralLabel();

    $permissions["view {$entity_type_id}"] = [
      'title' => $this->t('View @type', [
        '@type' => $plural_label,
      ]),
    ];
    foreach ($bundles as $bundle_name => $bundle_info) {
      $permissions["view {$bundle_name} {$entity_type_id}"] = [
        'title' => $this->t('@bundle: View @type', [
          '@bundle' => $bundle_info['label'],
          '@type' => $plural_label,
        ]),
      ];
    }

    return $permissions;
  }

}
