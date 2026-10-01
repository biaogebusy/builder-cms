<?php

declare(strict_types=1);

namespace Drupal\xinshi_api\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\simple_oauth\Authentication\TokenAuthUserInterface;

/**
 * Separates OAuth permission caches by account and grant identity.
 */
final class OAuthAccountCacheContext implements CacheContextInterface {

  public function __construct(private readonly AccountProxyInterface $currentUser) {}

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return new TranslatableMarkup('OAuth account and grant identity');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    $account = $this->currentUser->getAccount();
    if (!$account instanceof TokenAuthUserInterface) {
      return 'none';
    }
    // Client credentials and user tokens follow different permission rules.
    $kind = $account->getToken()->get('auth_user_id')->isEmpty() ? 'consumer' : 'user';
    return $kind . ':' . $account->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    $metadata = new CacheableMetadata();
    if ($this->currentUser->getAccount() instanceof TokenAuthUserInterface) {
      $metadata->addCacheTags(['user:' . $this->currentUser->id()]);
    }
    return $metadata;
  }

}
