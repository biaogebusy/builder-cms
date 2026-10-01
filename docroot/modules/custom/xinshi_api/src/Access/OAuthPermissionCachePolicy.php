<?php

declare(strict_types=1);

namespace Drupal\xinshi_api\Access;

use Drupal\Core\Session\AccessPolicyBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\RefinableCalculatedPermissionsInterface;
use Drupal\simple_oauth\Authentication\TokenAuthUserInterface;

/**
 * Adds cache variation without granting or altering any permissions.
 */
final class OAuthPermissionCachePolicy extends AccessPolicyBase {

  /**
   * {@inheritdoc}
   */
  public function applies(string $scope): bool {
    // Simple OAuth alters permissions in every access-policy scope.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function calculatePermissions(AccountInterface $account, string $scope): RefinableCalculatedPermissionsInterface {
    $permissions = parent::calculatePermissions($account, $scope);
    if ($account instanceof TokenAuthUserInterface) {
      // Active cache contexts do not bubble their tags automatically. Changing
      // a user's roles must invalidate this account's permission cache too.
      $permissions->addCacheTags(['user:' . $account->id()]);
    }
    return $permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function getPersistentCacheContexts(): array {
    // Scope-filtered roles alone do not identify the underlying user policy.
    return ['xinshi_oauth_account'];
  }

}
