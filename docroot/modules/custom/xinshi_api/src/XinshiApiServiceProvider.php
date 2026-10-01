<?php

declare(strict_types=1);

namespace Drupal\xinshi_api;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\xinshi_api\Access\OAuthPermissionCachePolicy;
use Drupal\xinshi_api\Cache\Context\OAuthAccountCacheContext;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers permission cache isolation when Simple OAuth is enabled.
 */
final class XinshiApiServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if (!$container->hasDefinition('access_policy.simple_oauth')) {
      return;
    }
    $container->register('cache_context.xinshi_oauth_account', OAuthAccountCacheContext::class)
      ->addArgument(new Reference('current_user'))
      ->addTag('cache.context');
    $container->register('xinshi_api.oauth_permission_cache_policy', OAuthPermissionCachePolicy::class)
      ->addTag('access_policy');
  }

}
