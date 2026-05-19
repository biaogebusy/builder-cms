<?php

namespace Drupal\Tests\simple_oauth_static_scope\Kernel;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\simple_oauth\Plugin\ScopeGranularity\Permission;
use Drupal\simple_oauth\Plugin\ScopeGranularity\Role;

/**
 * Tests for OAuth2 scope plugin.
 *
 * @group simple_oauth_static_scope
 */
class Oauth2ScopePluginTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization',
    'simple_oauth',
    'simple_oauth_static_scope',
    'simple_oauth_static_scope_test',
    'system',
    'user',
  ];

  /**
   * The scope granularity manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected PluginManagerInterface $granularityManager;

  /**
   * The OAuth2 scope manager.
   *
   * @var \Drupal\simple_oauth_static_scope\Plugin\Oauth2ScopeManagerInterface
   */
  protected $oauth2ScopeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['user']);

    $this->granularityManager = $this->container->get('plugin.manager.scope_granularity');
    $this->oauth2ScopeManager = $this->container->get('plugin.manager.oauth2_scope');
  }

  /**
   * Tests getter methods for the OAuth2 scope plugin.
   */
  public function testGettersPermission(): void {
    /** @var \Drupal\simple_oauth_static_scope\Plugin\Oauth2ScopePluginInterface $scope */
    $scope = $this->oauth2ScopeManager->getInstance(['id' => 'static_scope:child']);
    $expected_grant_types = [
      'authorization_code' => [
        'status' => TRUE,
        'description' => 'Test authorization_code description',
      ],
      'client_credentials' => [
        'status' => TRUE,
        'description' => 'Test client_credentials description',
      ],
    ];
    $this->assertEquals('static_scope:child', $scope->getName());
    $this->assertEquals('Test static:scope:child description', $scope->getDescription());
    $this->assertEquals($expected_grant_types, $scope->getGrantTypes());
    $this->assertEquals(FALSE, $scope->isUmbrella());
    $this->assertEquals('static_scope', $scope->getParent());
    $this->assertInstanceOf(Permission::class, $scope->getGranularity());
    $this->assertEquals(Oauth2ScopeInterface::GRANULARITY_PERMISSION, $scope->getGranularity()->getPluginId());
    $this->assertEquals(['permission' => 'debug simple_oauth tokens'], $scope->getGranularity()->getConfiguration());

    /** @var \Drupal\simple_oauth_static_scope\Plugin\Oauth2ScopePluginInterface $scope */
    $scope = $this->oauth2ScopeManager->getInstance(['id' => 'static_scope']);
    $this->assertEquals(TRUE, $scope->isUmbrella());
    $this->assertEmpty($scope->getGranularity());

    /** @var \Drupal\simple_oauth_static_scope\Plugin\Oauth2ScopePluginInterface $scope */
    $scope = $this->oauth2ScopeManager->getInstance(['id' => 'static_scope:role']);
    $this->assertInstanceOf(Role::class, $scope->getGranularity());
    $this->assertEquals(Oauth2ScopeInterface::GRANULARITY_ROLE, $scope->getGranularity()->getPluginId());
    $this->assertEquals(['role' => AccountInterface::AUTHENTICATED_ROLE], $scope->getGranularity()->getConfiguration());
  }

}
