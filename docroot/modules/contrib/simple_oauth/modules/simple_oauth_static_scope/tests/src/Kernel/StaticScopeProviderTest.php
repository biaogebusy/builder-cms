<?php

namespace Drupal\Tests\simple_oauth_static_scope\Kernel;

use Drupal\Tests\simple_oauth\Kernel\Oauth2ScopeProviderTestBase;
use Drupal\simple_oauth_static_scope\Plugin\Oauth2Scope;

/**
 * Tests Static OAuth2 Scope provider.
 *
 * @group simple_oauth_static_scope
 */
class StaticScopeProviderTest extends Oauth2ScopeProviderTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'simple_oauth_static_scope',
    'simple_oauth_static_scope_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('simple_oauth.settings')
      ->set('scope_provider', 'static')
      ->save();
  }

  /**
   * Tests static scope provider.
   */
  public function testStaticScopeProvider(): void {
    $this->assertScopeProvider(
      Oauth2Scope::class,
      [
        'static_scope' => [
          'name' => 'static_scope',
          'permissions' => [
            'access content',
            'debug simple_oauth tokens',
          ],
        ],
        'static_scope:child' => [
          'name' => 'static_scope:child',
          'permissions' => [
            'access content',
            'debug simple_oauth tokens',
          ],
        ],
        'static_scope:child:child' => [
          'name' => 'static_scope:child:child',
          'permissions' => [
            'access content',
          ],
        ],
        'static_scope:role' => [
          'name' => 'static_scope:role',
          'permissions' => [
            'access content',
            'debug simple_oauth tokens',
          ],
        ],
        'static_scope:role:child' => [
          'name' => 'static_scope:role:child',
          'permissions' => [
            'debug simple_oauth tokens',
          ],
        ],
      ]
    );
  }

}
