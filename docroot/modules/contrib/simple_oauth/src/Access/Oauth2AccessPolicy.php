<?php

declare(strict_types=1);

namespace Drupal\simple_oauth\Access;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccessPolicyBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\CalculatedPermissionsItem;
use Drupal\Core\Session\RefinableCalculatedPermissionsInterface;
use Drupal\simple_oauth\Authentication\TokenAuthUserInterface;
use Drupal\simple_oauth\Oauth2ScopeProviderInterface;

/**
 * Grants permissions based on OAuth2 scopes.
 */
final class Oauth2AccessPolicy extends AccessPolicyBase {

  public function __construct(protected Oauth2ScopeProviderInterface $scopeProvider, protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public function applies(string $scope): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterPermissions(AccountInterface $account, string $scope, RefinableCalculatedPermissionsInterface $calculated_permissions): void {
    if (!$account instanceof TokenAuthUserInterface) {
      return;
    }

    $token = $account->getToken();
    $oauth2_scopes = $token->get('scopes')->getScopes();
    $allowed_permissions = [];
    $cacheable_metadata = new CacheableMetadata();

    /** @var \Drupal\simple_oauth\Oauth2ScopeInterface $oauth2_scope */
    foreach ($oauth2_scopes as $oauth2_scope) {
      $allowed_permissions = array_merge($allowed_permissions, $this->scopeProvider->getPermissions($oauth2_scope));
      $cacheable_metadata
        ->addCacheableDependency($oauth2_scope)
        ->addCacheContexts($this->getPersistentCacheContexts());
    }

    /** @var \Drupal\Core\Session\CalculatedPermissionsItem $item */
    foreach ($calculated_permissions->getItems() as $item) {
      $permissions = $token->get('auth_user_id')->isEmpty() ? $allowed_permissions : array_intersect($item->getPermissions(), $allowed_permissions);
      $calculated_permissions
        ->addItem(
          item: new CalculatedPermissionsItem(
            permissions: $permissions,
            isAdmin: $item->isAdmin(),
            scope: $item->getScope(),
            identifier: $item->getIdentifier(),
          ),
          overwrite: TRUE,
        )
        ->addCacheableDependency($cacheable_metadata);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPersistentCacheContexts(): array {
    return ['oauth2_scopes'];
  }

}
