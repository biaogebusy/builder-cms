<?php

namespace Drupal\Tests\simple_oauth\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;

/**
 * OAuth2 Scope provider Test base.
 *
 * @group simple_oauth
 */
class Oauth2ScopeProviderTestBase extends KernelTestBase {

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
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installEntitySchema('consumer');
    $this->installConfig(['simple_oauth']);
    $this->installEntitySchema('user');
    $this->installConfig(['user']);

    $role = Role::load(AccountInterface::AUTHENTICATED_ROLE);
    $role->grantPermission('access content')->save();
  }

  /**
   * Assert the scope provider.
   *
   * @param string $expected_instance
   *   The expected scope instance.
   * @param array $expected_scopes
   *   The expected scopes:
   *    [
   *      'scope_id' => [
   *        'name' => '',
   *        'permissions' => []
   *      ]
   *    ].
   */
  protected function assertScopeProvider(string $expected_instance, array $expected_scopes): void {
    /** @var \Drupal\simple_oauth\Oauth2ScopeProvider $scope_provider */
    $scope_provider = \Drupal::service('simple_oauth.oauth2_scope.provider');

    // Test loading a single scope by id.
    $expected_first_scope_id = key($expected_scopes);
    $scope = $scope_provider->load($expected_first_scope_id);
    $this->assertInstanceOf($expected_instance, $scope);
    $this->assertEquals($expected_first_scope_id, $scope->id());
    $expected_first_scope = reset($expected_scopes);

    // Test loading a single scope by name.
    $scope = $scope_provider->loadByName($expected_first_scope['name']);
    $this->assertInstanceOf($expected_instance, $scope);
    $this->assertEquals($expected_first_scope['name'], $scope->getName());

    // Test loading all scopes.
    $all_scopes = $scope_provider->loadMultiple();
    $this->assertEquals(array_keys($expected_scopes), array_keys($all_scopes));
    foreach ($all_scopes as $scope) {
      $this->assertInstanceOf($expected_instance, $scope);
    }

    // Test load multiple specific scopes.
    $expected_first_two_scopes = array_slice($expected_scopes, 0, 2, TRUE);
    $expected_first_two_scope_ids = array_keys($expected_first_two_scopes);
    $scopes = $scope_provider->loadMultiple($expected_first_two_scope_ids);
    $this->assertCount(2, $scopes);
    foreach ($scopes as $scope) {
      $this->assertInstanceOf($expected_instance, $scope);
    }
    $this->assertArrayHasKey($expected_first_two_scope_ids[0], $scopes);
    $this->assertArrayHasKey($expected_first_two_scope_ids[1], $scopes);

    // Check if scope has permission.
    foreach ($all_scopes as $scope_id => $scope) {
      foreach ($expected_scopes[$scope_id]['permissions'] as $permission) {
        $this->assertTrue($scope_provider->scopeHasPermission($permission, $scope));
      }
    }
  }

}
