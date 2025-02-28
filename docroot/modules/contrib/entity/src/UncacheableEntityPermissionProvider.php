<?php

namespace Drupal\entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides generic entity permissions which are cached per user.
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
 * - view (own|any) ($bundle) $entity_type
 * - update (own|any) ($bundle) $entity_type
 * - duplicate (own|any) ($bundle) $entity_type
 * - delete (own|any) ($bundle) $entity_type
 * - create $bundle $entity_type
 *
 * Important:
 * Provides "view own ($bundle) $entity_type" permissions, which require
 * caching pages per user. This can significantly increase the size of caches,
 * impacting site performance. Use \Drupal\entity\EntityPermissionProvider
 * if those permissions are not necessary.
 *
 * Example annotation:
 * @code
 *  handlers = {
 *    "access" = "Drupal\entity\UncacheableEntityAccessControlHandler",
 *    "permission_provider" = "Drupal\entity\UncacheableEntityPermissionProvider",
 *  }
 * @endcode
 *
 * @see \Drupal\entity\EntityAccessControlHandler
 * @see \Drupal\entity\EntityPermissions
 */
class UncacheableEntityPermissionProvider extends EntityPermissionProviderBase {

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
    $has_owner = $entity_type->entityClassImplements(EntityOwnerInterface::class);
    $plural_label = $entity_type->getPluralLabel();

    if ($has_owner) {
      $permissions["view any {$entity_type_id}"] = [
        'title' => $this->t('View any @type', [
          '@type' => $plural_label,
        ]),
      ];
      $permissions["view own {$entity_type_id}"] = [
        'title' => $this->t('View own @type', [
          '@type' => $plural_label,
        ]),
      ];
    }
    else {
      $permissions["view {$entity_type_id}"] = [
        'title' => $this->t('View @type', [
          '@type' => $plural_label,
        ]),
      ];
    }

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
    $has_owner = $entity_type->entityClassImplements(EntityOwnerInterface::class);
    $plural_label = $entity_type->getPluralLabel();

    if ($has_owner) {
      $permissions["view any {$entity_type_id}"] = [
        'title' => $this->t('View any @type', [
          '@type' => $plural_label,
        ]),
      ];
      $permissions["view own {$entity_type_id}"] = [
        'title' => $this->t('View own @type', [
          '@type' => $plural_label,
        ]),
      ];
    }
    else {
      $permissions["view {$entity_type_id}"] = [
        'title' => $this->t('View @type', [
          '@type' => $plural_label,
        ]),
      ];
    }

    foreach ($bundles as $bundle_name => $bundle_info) {
      if ($has_owner) {
        $permissions["view any {$bundle_name} {$entity_type_id}"] = [
          'title' => $this->t('@bundle: View any @type', [
            '@bundle' => $bundle_info['label'],
            '@type' => $plural_label,
          ]),
        ];
        $permissions["view own {$bundle_name} {$entity_type_id}"] = [
          'title' => $this->t('@bundle: View own @type', [
            '@bundle' => $bundle_info['label'],
            '@type' => $plural_label,
          ]),
        ];
      }
      else {
        $permissions["view {$bundle_name} {$entity_type_id}"] = [
          'title' => $this->t('@bundle: View @type', [
            '@bundle' => $bundle_info['label'],
            '@type' => $plural_label,
          ]),
        ];
      }
    }

    return $permissions;
  }

}
