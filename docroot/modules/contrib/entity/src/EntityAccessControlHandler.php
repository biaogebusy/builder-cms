<?php

namespace Drupal\entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Controls access based on the generic entity permissions.
 *
 * @see \Drupal\entity\UncacheableEntityPermissionProvider
 */
class EntityAccessControlHandler extends EntityAccessControlHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeInterface $entity_type) {
    parent::__construct($entity_type);

    if (!$entity_type->hasHandlerClass('permission_provider') || !is_a($entity_type->getHandlerClass('permission_provider'), EntityPermissionProvider::class, TRUE)) {
      throw new \Exception('\Drupal\entity\EntityAccessControlHandler requires the \Drupal\entity\EntityPermissionProvider permission provider.');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkEntityOwnerPermissions(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\user\EntityOwnerInterface $entity */
    if ($operation === 'view') {
      if ($entity instanceof EntityPublishedInterface && !$entity->isPublished()) {
        if ($account->id() != $entity->getOwnerId()) {
          // There's no permission for viewing other user's unpublished entity.
          return AccessResult::neutral()->cachePerUser();
        }

        $permissions = [
          "view own unpublished {$entity->getEntityTypeId()}",
        ];
        $result = AccessResult::allowedIfHasPermissions($account, $permissions)->cachePerUser();
      }
      else {
        $result = AccessResult::allowedIfHasPermissions($account, [
          "view {$entity->getEntityTypeId()}",
          "view {$entity->bundle()} {$entity->getEntityTypeId()}",
        ], 'OR');
      }
    }
    else {
      $result = parent::checkEntityOwnerPermissions($entity, $operation, $account);
    }

    return $result;
  }

}
