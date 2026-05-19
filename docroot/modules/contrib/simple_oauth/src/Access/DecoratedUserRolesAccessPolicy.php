<?php

namespace Drupal\simple_oauth\Access;

use Drupal\Core\Session\AccessPolicyBase;
use Drupal\Core\Session\AccessPolicyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\CalculatedPermissionsItem;
use Drupal\Core\Session\RefinableCalculatedPermissionsInterface;
use Drupal\simple_oauth\Authentication\TokenAuthUserInterface;

/**
 * Decorates the user.roles access policy.
 *
 * Due to SA-CONTRIB-2025-114, getRoles() is now decorated and limited by
 * configured role scopes, which restricts the permissions retrieved by the
 * user.roles access policy. This access policy bypasses the decorator
 * (TokenAuthUser) and calls getRoles() on the original service to ensure all
 * associated roles are correctly retrieved.
 */
class DecoratedUserRolesAccessPolicy extends AccessPolicyBase {

  /**
   * Constructs a new DecoratedUserRolesAccessPolicy.
   */
  public function __construct(protected AccessPolicyInterface $inner, protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public function calculatePermissions(AccountInterface $account, string $scope): RefinableCalculatedPermissionsInterface {
    if (!$account instanceof TokenAuthUserInterface) {
      return $this->inner->calculatePermissions($account, $scope);
    }

    $calculated_permissions = parent::calculatePermissions($account, $scope);

    /** @var \Drupal\user\RoleInterface[] $user_roles */
    $user_roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple($account->getSubject()->getRoles());

    foreach ($user_roles as $user_role) {
      $calculated_permissions
        ->addItem(new CalculatedPermissionsItem($user_role->getPermissions(), $user_role->isAdmin()))
        ->addCacheableDependency($user_role);
    }

    return $calculated_permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function getPersistentCacheContexts(): array {
    return $this->inner->getPersistentCacheContexts();
  }

}
