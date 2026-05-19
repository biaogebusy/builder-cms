<?php

namespace Drupal\Tests\simple_oauth_static_scope\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simple_oauth_static_scope\Plugin\Oauth2Scope;
use Drupal\user\Entity\Role;

/**
 * Tests OAuth2 Scope provider.
 *
 * @group simple_oauth
 */
class Oauth2ScopeProviderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'consumers',
    'file',
    'image',
    'options',
    'serialization',
    'system',
    'simple_oauth',
    'simple_oauth_static_scope',
    'simple_oauth_static_scope_test',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('oauth2_scope');
    $this->installEntitySchema('file');
    $this->installEntitySchema('consumer');
    $this->installConfig(['simple_oauth']);
    $this->installEntitySchema('user');
    $this->installConfig(['user']);

    $role = Role::load(AccountInterface::AUTHENTICATED_ROLE);
    $role->grantPermission('access content')->save();

    $this->config('simple_oauth.settings')
      ->set('scope_provider', 'static')
      ->save();
  }

  /**
   * Tests plugin/static scope provider.
   */
  public function testScopeProvider(): void {
    /** @var \Drupal\simple_oauth\Oauth2ScopeProvider $scope_provider */
    $scope_provider = \Drupal::service('simple_oauth.oauth2_scope.provider');
    // Test loading a single scope.
    $scope = $scope_provider->load('static_scope');
    $this->assertInstanceOf(Oauth2Scope::class, $scope);
    $scope = $scope_provider->loadByName('static_scope:child');
    $this->assertInstanceOf(Oauth2Scope::class, $scope);
    // Test loading all scopes.
    $all_scopes = $scope_provider->loadMultiple();
    $this->assertCount(5, $all_scopes);
    $this->assertEquals([
      'static_scope',
      'static_scope:child',
      'static_scope:child:child',
      'static_scope:role',
      'static_scope:role:child',
    ], array_keys($all_scopes));
    foreach ($all_scopes as $scope) {
      $this->assertInstanceOf(Oauth2Scope::class, $scope);
    }
    // Test load multiple specific scopes.
    $scopes = $scope_provider->loadMultiple([
      'static_scope',
      'static_scope:role',
    ]);
    $this->assertCount(2, $scopes);
    foreach ($scopes as $scope) {
      $this->assertInstanceOf(Oauth2Scope::class, $scope);
    }

    // Test retrieving flatten permission tree.
    $scopes_indexed = array_values($all_scopes);

    foreach (['access content', 'debug simple_oauth tokens'] as $expected_permission) {
      // The first scope is an umbrella scope.
      $this->assertTrue($scope_provider->scopeHasPermission($expected_permission, $scopes_indexed[0]));
      // Second scope gives back its own permission.
      $this->assertTrue($scope_provider->scopeHasPermission($expected_permission, $scopes_indexed[1]));
      // Fourth scope gives back permissions referenced by the associated role
      // and underlying child permission.
      $this->assertTrue($scope_provider->scopeHasPermission($expected_permission, $scopes_indexed[3]));
    }

    // Third scope gives back its own permission.
    $this->assertTrue($scope_provider->scopeHasPermission('access content', $scopes_indexed[2]));
    $this->assertFalse($scope_provider->scopeHasPermission('debug simple_oauth tokens', $scopes_indexed[2]));

    // Fifth scope gives back its own permission.
    $this->assertTrue($scope_provider->scopeHasPermission('debug simple_oauth tokens', $scopes_indexed[4]));
    $this->assertFalse($scope_provider->scopeHasPermission('access content', $scopes_indexed[4]));
  }

}
