<?php

namespace Drupal\Tests\simple_oauth\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\simple_oauth\Oauth2ScopeInterface;

/**
 * Tests Dynamic OAuth2 Scope provider.
 *
 * @group simple_oauth
 */
class DynamicScopeProviderTest extends Oauth2ScopeProviderTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    Oauth2Scope::create([
      'name' => 'dynamic_scope',
      'description' => 'Dynamic scope description',
      'grant_types' => [
        'authorization_code' => [
          'status' => TRUE,
          'description' => 'Test authorization_code description',
        ],
      ],
      'umbrella' => TRUE,
    ])->save();
    Oauth2Scope::create([
      'name' => 'dynamic_scope:child',
      'description' => 'Dynamic scope child description',
      'grant_types' => [
        'authorization_code' => [
          'status' => TRUE,
          'description' => 'Test authorization_code description',
        ],
      ],
      'umbrella' => FALSE,
      'parent' => 'dynamic_scope',
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => [
        'permission' => 'debug simple_oauth tokens',
      ],
    ])->save();
    Oauth2Scope::create([
      'name' => 'dynamic_scope:child:child',
      'description' => 'Dynamic scope child:child description',
      'grant_types' => [
        'authorization_code' => [
          'status' => TRUE,
          'description' => 'Test authorization_code description',
        ],
      ],
      'umbrella' => FALSE,
      'parent' => 'dynamic_scope_child',
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => [
        'permission' => 'access content',
      ],
    ])->save();
    Oauth2Scope::create([
      'name' => 'dynamic_scope:role',
      'description' => 'Dynamic scope dynamic_scope:role description',
      'grant_types' => [
        'authorization_code' => [
          'status' => TRUE,
          'description' => 'Test authorization_code description',
        ],
      ],
      'umbrella' => FALSE,
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_ROLE,
      'granularity_configuration' => [
        'role' => AccountInterface::AUTHENTICATED_ROLE,
      ],
    ])->save();
    Oauth2Scope::create([
      'name' => 'dynamic_scope:role:child',
      'description' => 'Dynamic scope dynamic_scope:role:child description',
      'grant_types' => [
        'authorization_code' => [
          'status' => TRUE,
          'description' => 'Test authorization_code description',
        ],
      ],
      'umbrella' => FALSE,
      'parent' => 'dynamic_scope_role',
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => [
        'permission' => 'debug simple_oauth tokens',
      ],
    ])->save();
  }

  /**
   * Tests dynamic scope provider.
   */
  public function testDynamicScopeProvider(): void {
    $this->assertScopeProvider(
      Oauth2Scope::class,
      [
        'dynamic_scope' => [
          'name' => 'dynamic_scope',
          'permissions' => [
            'access content',
            'debug simple_oauth tokens',
          ],
        ],
        'dynamic_scope_child' => [
          'name' => 'dynamic_scope:child',
          'permissions' => [
            'access content',
            'debug simple_oauth tokens',
          ],
        ],
        'dynamic_scope_child_child' => [
          'name' => 'dynamic_scope:child:child',
          'permissions' => [
            'access content',
          ],
        ],
        'dynamic_scope_role' => [
          'name' => 'dynamic_scope:role',
          'permissions' => [
            'access content',
            'debug simple_oauth tokens',
          ],
        ],
        'dynamic_scope_role_child' => [
          'name' => 'dynamic_scope:role:child',
          'permissions' => [
            'debug simple_oauth tokens',
          ],
        ],
      ]
    );
  }

}
