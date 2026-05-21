<?php

namespace Drupal\Tests\simple_oauth_static_scope\Kernel;

use Drupal\Tests\simple_oauth\Kernel\Oauth2ScopeReferenceItemTestBase;

/**
 * Tests the OAuth2 scope reference field type with static scopes.
 *
 * @group simple_oauth
 */
class StaticScopeReferenceItemTest extends Oauth2ScopeReferenceItemTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'simple_oauth_static_scope',
    'simple_oauth_static_scope_test',
  ];

  /**
   * Test reference with static OAuth2 scopes.
   */
  public function testStaticOauth2ScopeReferenceItem(): void {
    // Enable static scope provider.
    $this->config('simple_oauth.settings')
      ->set('scope_provider', 'static')
      ->save();

    $this->assertOauth2ScopeReferenceItems([
      'static_scope',
      'static_scope:child',
    ]);
  }

}
