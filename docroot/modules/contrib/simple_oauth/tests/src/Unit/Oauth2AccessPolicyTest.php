<?php

declare(strict_types=1);

namespace Drupal\Tests\simple_oauth\Unit;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccessPolicyInterface;
use Drupal\Core\Session\CalculatedPermissionsItem;
use Drupal\Core\Session\RefinableCalculatedPermissions;
use Drupal\simple_oauth\Authentication\TokenAuthUserInterface;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\simple_oauth\Entity\Oauth2TokenInterface;
use Drupal\simple_oauth\Access\Oauth2AccessPolicy;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\simple_oauth\Oauth2ScopeProviderInterface;
use Drupal\simple_oauth\Plugin\Field\FieldType\Oauth2ScopeReferenceItemListInterface;
use Drupal\simple_oauth\Plugin\ScopeGranularityInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\simple_oauth\Access\Oauth2AccessPolicy
 * @group simple_oauth
 */
class Oauth2AccessPolicyTest extends UnitTestCase {

  /**
   * The mocked scope provider service.
   *
   * @var \Drupal\simple_oauth\Oauth2ScopeProviderInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $scopeProvider;

  /**
   * The access policy to test.
   *
   * @var \Drupal\simple_oauth\Access\Oauth2AccessPolicy
   */
  protected $accessPolicy;

  /**
   * The mocked entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->scopeProvider = $this->prophesize(Oauth2ScopeProviderInterface::class);
    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->accessPolicy = new Oauth2AccessPolicy($this->scopeProvider->reveal(), $this->entityTypeManager->reveal());

    $cache_context_manager = $this->prophesize(CacheContextsManager::class);
    $cache_context_manager->assertValidTokens(Argument::any())->willReturn(TRUE);

    $container = $this->prophesize(ContainerInterface::class);
    $container->get('cache_contexts_manager')->willReturn($cache_context_manager->reveal());
    \Drupal::setContainer($container->reveal());
  }

  /**
   * @covers ::applies
   */
  public function testApplies(): void {
    $this->assertTrue($this->accessPolicy->applies(AccessPolicyInterface::SCOPE_DRUPAL));
    $this->assertTrue($this->accessPolicy->applies('another scope'));
    $this->assertTrue($this->accessPolicy->applies($this->randomString()));
  }

  /**
   * Data provider for testCalculatePermissions.
   *
   * @return array
   *   A list of test scenarios.
   */
  public static function alterPermissionsProvider(): array {
    $cases['permission-scope'] = [
      'roles' => [],
      'expect_admin_rights' => FALSE,
      'policy_scope' => 'bar',
      'oauth2_scopes' => [
        'scope_bar' => [
          'granularity' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
          'granularity_configuration' => [
            'permission' => 'bar',
          ],
        ],
      ],
    ];
    $cases['role-and-permission-scope'] = [
      'roles' => [
        'role_foo' => [
          'permissions' => ['foo'],
          'is_admin' => FALSE,
        ],
        'role_bar_baz' => [
          'permissions' => ['bar', 'baz'],
          'is_admin' => FALSE,
        ],
      ],
      'expect_admin_rights' => FALSE,
      'policy_scope' => 'foo',
      'oauth2_scopes' => [
        'scope_bar_baz' => [
          'granularity' => Oauth2ScopeInterface::GRANULARITY_ROLE,
          'granularity_configuration' => [
            'role' => 'role_bar_baz',
          ],
        ],
        'scope_foo' => [
          'granularity' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
          'granularity_configuration' => [
            'permission' => 'foo',
          ],
        ],
      ],
    ];
    $cases['admin-role-scope'] = [
      'roles' => [
        'role_foo' => [
          'permissions' => ['foo'],
          'is_admin' => FALSE,
        ],
        'role_bar' => [
          'permissions' => ['bar'],
          'is_admin' => TRUE,
        ],
      ],
      'expect_admin_rights' => TRUE,
      'policy_scope' => AccessPolicyInterface::SCOPE_DRUPAL,
      'oauth2_scopes' => [
        'scope_bar' => [
          'granularity' => Oauth2ScopeInterface::GRANULARITY_ROLE,
          'granularity_configuration' => [
            'role' => 'role_bar',
          ],
        ],
      ],
    ];
    return $cases;
  }

  /**
   * Tests the alterPermissions method.
   *
   * @param array $roles
   *   The available roles.
   * @param bool $expect_admin_rights
   *   Whether to expect admin rights to be granted.
   * @param string $policy_scope
   *   The access policy scope.
   * @param array $oauth2_scopes
   *   The scopes to grant the account.
   *
   * @covers ::alterPermissions
   * @dataProvider alterPermissionsProvider
   */
  public function testAlterPermissions(array $roles, bool $expect_admin_rights, string $policy_scope, array $oauth2_scopes): void {
    $account = $this->prophesize(TokenAuthUserInterface::class);
    $user = $this->prophesize(UserInterface::class);
    $user->getRoles()->willReturn(array_keys($roles));
    $account->getSubject()->willReturn($user->reveal());
    $token = $this->prophesize(Oauth2TokenInterface::class);
    $auth_id_field = $this->prophesize(FieldItemListInterface::class);
    $auth_id_field->isEmpty()->willReturn(FALSE);
    $token->get('auth_user_id')->willReturn($auth_id_field->reveal());
    $scopes_field = $this->prophesize(Oauth2ScopeReferenceItemListInterface::class);

    $total_permissions = $cache_tags = $mocked_scopes = [];
    foreach ($oauth2_scopes as $oauth2_scope_id => $scope) {
      $scope_permissions = [];
      if ($scope['granularity'] === Oauth2ScopeInterface::GRANULARITY_PERMISSION) {
        $scope_permissions = [$scope['granularity_configuration']['permission']];
        $total_permissions = array_merge($total_permissions, $scope_permissions);
      }
      elseif ($scope['granularity'] === Oauth2ScopeInterface::GRANULARITY_ROLE) {
        $role = $scope['granularity_configuration']['role'];
        $scope_permissions = $roles[$role]['permissions'];
        $total_permissions = array_merge($total_permissions, $scope_permissions);
      }

      $cache_tags[] = "oauth2_scopes.$oauth2_scope_id";

      $mocked_scope = $this->prophesize(Oauth2Scope::class);
      $scope_granularity = $this->prophesize(ScopeGranularityInterface::class);
      $scope_granularity->getPluginId()->willReturn($scope['granularity']);
      $scope_granularity->getConfiguration()->willReturn($scope['granularity_configuration']);
      $mocked_scope->getGranularity()->willReturn($scope_granularity->reveal());
      $mocked_scope->getCacheTags()->willReturn(["oauth2_scopes.$oauth2_scope_id"]);
      $mocked_scope->getCacheContexts()->willReturn([]);
      $mocked_scope->getCacheMaxAge()->willReturn(Cache::PERMANENT);
      $mocked_scopes[$oauth2_scope_id] = $mocked_scope->reveal();
      $this->scopeProvider->getPermissions($mocked_scope->reveal())->willReturn($scope_permissions);
    }

    $scopes_field->getScopes()->willReturn($mocked_scopes);
    $token->get('scopes')->willReturn($scopes_field->reveal());
    $account->getToken()->willReturn($token->reveal());

    $calculated_permissions = new RefinableCalculatedPermissions();
    $calculated_permissions->addItem(new CalculatedPermissionsItem($total_permissions, $expect_admin_rights));
    $this->accessPolicy->alterPermissions($account->reveal(), $policy_scope, $calculated_permissions);

    if (!empty($roles)) {
      $this->assertCount(1, $calculated_permissions->getItems(), 'Only one calculated permissions item was added.');
      $item = $calculated_permissions->getItem();

      if ($expect_admin_rights) {
        $this->assertSame([], $item->getPermissions());
        $this->assertTrue($item->isAdmin());
      }
      else {
        $this->assertSame($total_permissions, $item->getPermissions());
        $this->assertFalse($item->isAdmin());
      }
    }

    $this->assertSame($cache_tags, $calculated_permissions->getCacheTags());
    $this->assertSame(['oauth2_scopes'], $calculated_permissions->getCacheContexts());
    $this->assertSame(Cache::PERMANENT, $calculated_permissions->getCacheMaxAge());
  }

  /**
   * Tests the getPersistentCacheContexts method.
   *
   * @covers ::getPersistentCacheContexts
   */
  public function testGetPersistentCacheContexts(): void {
    $this->assertSame(['oauth2_scopes'], $this->accessPolicy->getPersistentCacheContexts());
  }

}
