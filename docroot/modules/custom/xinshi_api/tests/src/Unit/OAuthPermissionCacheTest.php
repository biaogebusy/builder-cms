<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_api\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Cache\Context\IsSuperUserCacheContext;
use Drupal\Core\Cache\Context\UserRolesCacheContext;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Cache\VariationCache;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccessPolicyProcessor;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Session\PermissionChecker;
use Drupal\Core\Session\SuperUserAccessPolicy;
use Drupal\Core\Session\UserRolesAccessPolicy;
use Drupal\consumers\Entity\Consumer;
use Drupal\simple_oauth\Access\DecoratedUserRolesAccessPolicy;
use Drupal\simple_oauth\Access\Oauth2AccessPolicy;
use Drupal\simple_oauth\Authentication\TokenAuthUser;
use Drupal\simple_oauth\Cache\Oauth2ScopeCacheContext;
use Drupal\simple_oauth\Entity\Oauth2TokenInterface;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\simple_oauth\Oauth2ScopeProviderInterface;
use Drupal\simple_oauth\Plugin\Field\FieldType\Oauth2ScopeReferenceItemListInterface;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;
use Drupal\xinshi_api\Access\OAuthPermissionCachePolicy;
use Drupal\xinshi_api\XinshiApiServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;

/** Exercises the real permission processor, OAuth policies and cache contexts. */
final class OAuthPermissionCacheTest extends TestCase {

  private AccountProxy $current;
  private MemoryBackend $static;
  private MemoryBackend $persistent;
  private AccessPolicyProcessor $processor;

  protected function setUp(): void {
    $this->current = new AccountProxy(new EventDispatcher());
    $container = new ContainerBuilder();
    $container->set('current_user', $this->current);
    $container->register('access_policy.simple_oauth', Oauth2AccessPolicy::class);
    (new XinshiApiServiceProvider())->alter($container);
    $contexts = [
      'xinshi_oauth_account' => $container->get('cache_context.xinshi_oauth_account'),
      'user.roles' => new UserRolesCacheContext($this->current),
      'user.is_super_user' => new IsSuperUserCacheContext($this->current),
      'oauth2_scopes' => new Oauth2ScopeCacheContext($this->current),
    ];
    foreach ($contexts as $id => $context) {
      $container->set('cache_context.' . $id, $context);
    }
    $manager = new CacheContextsManager($container, array_keys($contexts));
    $container->set('cache_contexts_manager', $manager);
    \Drupal::setContainer($container);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(1700000000);
    $time->method('getRequestTime')->willReturn(1700000000);
    $this->static = new MemoryBackend($time);
    $this->processor = new AccessPolicyProcessor(
      new VariationCache(new RequestStack(), $this->persistent = new MemoryBackend($time), $manager),
      new VariationCache(new RequestStack(), $this->static, $manager),
      $this->static, $this->current, $this->createMock(AccountSwitcherInterface::class),
    );
    $roles = [];
    foreach (['anonymous', 'authenticated', 'webmaster', 'administrator'] as $id) {
      $role = $this->createMock(RoleInterface::class);
      $role->method('getPermissions')->willReturn($id === 'webmaster' ? ['access content', 'view site ai usage'] : ['access content']);
      $role->method('isAdmin')->willReturn($id === 'administrator');
      $role->method('getCacheContexts')->willReturn([]);
      $role->method('getCacheTags')->willReturn(['config:user.role.' . $id]);
      $role->method('getCacheMaxAge')->willReturn(-1);
      $roles[$id] = $role;
    }
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturnCallback(fn($ids) => array_intersect_key($roles, array_flip($ids)));
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('user_role')->willReturn($storage);
    $provider = $this->createMock(Oauth2ScopeProviderInterface::class);
    $provider->method('getPermissions')->willReturnCallback(static fn($scope) => $scope->getName() === 'reporting' ? ['access content', 'view site ai usage'] : ['access content']);
    $this->processor->addAccessPolicy(new SuperUserAccessPolicy());
    $this->processor->addAccessPolicy(new DecoratedUserRolesAccessPolicy(new UserRolesAccessPolicy($entities), $entities, $this->createMock(ConfigInstallerInterface::class)));
    $this->processor->addAccessPolicy(new Oauth2AccessPolicy($provider, $entities));
    $this->processor->addAccessPolicy($container->get('xinshi_api.oauth_permission_cache_policy'));
  }

  public static function orders(): array {
    return [
      'ordinary then admin, persistent' => [[FALSE, TRUE], TRUE],
      'admin then ordinary, persistent' => [[TRUE, FALSE], TRUE],
      'ordinary then admin, memory' => [[FALSE, TRUE], FALSE],
      'admin then ordinary, memory' => [[TRUE, FALSE], FALSE],
    ];
  }

  #[DataProvider('orders')]
  public function testPermissionsDoNotCrossAccounts(array $order, bool $resetStatic): void {
    foreach ($order as $isAdmin) {
      $account = $this->account($isAdmin);
      $this->current->setAccount($account);
      if ($resetStatic) {
        $this->static->deleteAll();
      }
      foreach (['administer xinshi_ai', 'view site ai usage', 'view ai supplier costs'] as $permission) {
        $this->assertSame($isAdmin, $account->hasPermission($permission), $isAdmin ? 'Admin must keep privileges after an ordinary user fills the cache' : 'Ordinary user must not inherit cached admin privileges');
      }
    }
  }

  public function testChangingScopesKeepsTheirPermissionLimits(): void {
    foreach ([['webmaster', FALSE], ['reporting', TRUE], ['webmaster', FALSE]] as [$scope, $allowed]) {
      $this->assertSame($allowed, $this->hasPermission($this->account(FALSE, [$scope]), 'view site ai usage'));
    }
  }

  public static function authenticationOrders(): array {
    return ['restricted first' => [[FALSE, TRUE]], 'privileged first' => [[TRUE, FALSE]]];
  }

  #[DataProvider('authenticationOrders')]
  public function testCookieAndEmptyScopeTokenAreSeparate(array $order): void {
    foreach ($order as $cookie) {
      $tokenUser = $this->account(FALSE, []);
      $account = $cookie ? $tokenUser->getSubject() : $tokenUser;
      $this->assertSame($cookie, $this->hasPermission($account, 'view site ai usage'));
    }
  }

  #[DataProvider('authenticationOrders')]
  public function testUserAndConsumerTokensAreSeparate(array $order): void {
    foreach ($order as $consumer) {
      // The same account and scope: only client credentials take scope grants
      // directly; a user token remains limited to the subject's permissions.
      $account = $this->account(FALSE, ['reporting'], $consumer, FALSE);
      $this->assertSame($consumer, $this->hasPermission($account, 'view site ai usage'));
    }
  }

  public function testAnonymousDoesNotReuseAdminPermissions(): void {
    $this->assertTrue($this->hasPermission($this->account(TRUE), 'administer xinshi_ai'));
    $this->assertFalse($this->hasPermission(new AnonymousUserSession(), 'administer xinshi_ai'));
  }

  public function testRoleRevocationInvalidatesTheAccountPermissions(): void {
    $this->assertTrue($this->hasPermission($this->account(TRUE), 'administer xinshi_ai'));
    $this->persistent->invalidateTags(['user:24']);
    $this->static->invalidateTags(['user:24']);
    $this->assertFalse($this->hasPermission($this->account(FALSE, uid: 24), 'administer xinshi_ai'));
  }

  public function testPolicyDoesNotGrantOrAlterPermissions(): void {
    $policy = new OAuthPermissionCachePolicy();
    $this->assertTrue($policy->applies('another_scope'));
    $result = $policy->calculatePermissions($this->account(FALSE), 'drupal');
    $this->assertSame([], $result->getItems());
    $this->assertSame(['xinshi_oauth_account'], $result->getCacheContexts());
    $policy->alterPermissions($this->account(FALSE), 'drupal', $result);
    $this->assertSame([], $result->getItems());
  }

  public function testOptionalOauthServicesAreNotRegisteredWhenDisabled(): void {
    $container = new ContainerBuilder();
    (new XinshiApiServiceProvider())->alter($container);
    $this->assertFalse($container->hasDefinition('cache_context.xinshi_oauth_account'));
    $this->assertFalse($container->hasDefinition('xinshi_api.oauth_permission_cache_policy'));
  }

  public function testAccountContextPreservesRevocationTags(): void {
    $account = $this->account(TRUE);
    $this->current->setAccount($account);
    $context = \Drupal::service('cache_context.xinshi_oauth_account');
    $this->assertSame('user:24', $context->getContext());
    $this->assertSame(['user:24'], $context->getCacheableMetadata()->getCacheTags());
    $this->current->setAccount($account->getSubject());
    $this->assertSame('none', $context->getContext());
  }

  private function hasPermission(AccountInterface $account, string $permission): bool {
    $this->current->setAccount($account);
    $this->static->deleteAll();
    return $this->processor->processAccessPolicies($account)->getItem()->hasPermission($permission);
  }

  private function account(bool $admin, array $scopeNames = ['webmaster'], bool $consumer = FALSE, bool $webmaster = TRUE, ?int $uid = NULL): TokenAuthUser {
    $subject = $this->createMock(UserInterface::class);
    $subject->method('id')->willReturn($uid ?? ($admin ? 24 : 25));
    $subject->method('isAuthenticated')->willReturn(TRUE);
    $subject->method('getRoles')->willReturn($admin ? ['authenticated', 'webmaster', 'administrator'] : ($webmaster ? ['authenticated', 'webmaster'] : ['authenticated']));
    $client = $this->createMock(FieldItemListInterface::class);
    $consumerEntity = $this->createMock(Consumer::class);
    $consumerUser = $this->createMock(FieldItemListInterface::class);
    $consumerUser->method('__get')->with('entity')->willReturn($subject);
    $consumerEntity->method('get')->with('user_id')->willReturn($consumerUser);
    $client->method('__get')->with('entity')->willReturn($consumerEntity);
    $authUser = $this->createMock(FieldItemListInterface::class);
    $authUser->method('__get')->with('entity')->willReturn($consumer ? NULL : $subject);
    $authUser->method('isEmpty')->willReturn($consumer);
    $scopeItems = [];
    foreach ($scopeNames as $scopeName) {
      $scope = $this->createMock(Oauth2ScopeInterface::class);
      $scope->method('getName')->willReturn($scopeName);
      $scopeItems[] = $scope;
    }
    $scopes = $this->createMock(Oauth2ScopeReferenceItemListInterface::class);
    $scopes->method('getScopes')->willReturn($scopeItems);
    $token = $this->createMock(Oauth2TokenInterface::class);
    $token->method('get')->willReturnMap([['client', $client], ['auth_user_id', $authUser], ['scopes', $scopes]]);
    $token->method('getRoles')->willReturn($scopeNames ? ['authenticated', 'webmaster'] : ['authenticated']);
    return new TokenAuthUser(new PermissionChecker($this->processor), $token, $this->createMock(HttpMessageFactoryInterface::class), new RequestStack());
  }
}
