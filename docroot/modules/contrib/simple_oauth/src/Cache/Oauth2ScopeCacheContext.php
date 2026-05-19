<?php

namespace Drupal\simple_oauth\Cache;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CalculatedCacheContextInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\simple_oauth\Authentication\TokenAuthUserInterface;
use Drupal\simple_oauth\Oauth2ScopeInterface;

/**
 * Defines the Oauth2ScopeCacheContext service, for "per scope" caching.
 */
class Oauth2ScopeCacheContext implements CalculatedCacheContextInterface {

  /**
   * Constructs a new Oauth2ScopeCacheContext class.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   */
  public function __construct(protected AccountProxyInterface $account) {}

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t("OAuth2 scopes");
  }

  /**
   * {@inheritdoc}
   */
  public function getContext($oauth2_scope = NULL) {
    $account = $this->account->getAccount();
    if (!$account instanceof TokenAuthUserInterface) {
      return '';
    }

    $token = $account->getToken();
    $scope_names = array_map(function (Oauth2ScopeInterface $scope) {
      return $scope->getName();
    }, $token->get('scopes')->getScopes());

    if ($oauth2_scope === NULL) {
      return implode(',', $scope_names);
    }
    return (in_array($oauth2_scope, $scope_names) ? 'true' : 'false');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($oauth2_scope = NULL) {
    return (new CacheableMetadata())->setCacheTags(['user:' . $this->account->id()]);
  }

}
