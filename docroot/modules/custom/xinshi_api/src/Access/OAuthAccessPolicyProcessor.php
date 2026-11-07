<?php

declare(strict_types=1);

namespace Drupal\xinshi_api\Access;

use Drupal\Core\Session\AccessPolicyInterface;
use Drupal\Core\Session\AccessPolicyProcessorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\CalculatedPermissionsInterface;
use Drupal\simple_oauth\Authentication\TokenAuthUserInterface;

/** Preserves OAuth identity when core evaluates current_user cache contexts. */
final class OAuthAccessPolicyProcessor implements AccessPolicyProcessorInterface {

  public function __construct(private readonly AccessPolicyProcessorInterface $inner) {}

  public function addAccessPolicy(AccessPolicyInterface $access_policy): void {
    $this->inner->addAccessPolicy($access_policy);
  }

  public function processAccessPolicies(AccountInterface $account, string $scope = AccessPolicyInterface::SCOPE_DRUPAL): CalculatedPermissionsInterface {
    // AccountProxy hides the token type from Simple OAuth's policies. Unwrap
    // only the proxy, retaining the token and its scope, never the full user.
    if ($account instanceof AccountProxyInterface && $account->getAccount() instanceof TokenAuthUserInterface) {
      $account = $account->getAccount();
    }
    return $this->inner->processAccessPolicies($account, $scope);
  }

}
