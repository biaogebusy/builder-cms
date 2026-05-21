<?php

namespace Drupal\Tests\simple_oauth\Kernel;

use Drupal\simple_oauth\Entity\Oauth2Scope as Oauth2ScopeEntity;

/**
 * Tests the OAuth2 scope reference field type with dynamic scopes.
 *
 * @group simple_oauth
 */
class DynamicScopeReferenceItemTest extends Oauth2ScopeReferenceItemTestBase {

  /**
   * Test reference with dynamic OAuth2 scopes.
   */
  public function testDynamicOauth2ScopeReferenceItem(): void {
    Oauth2ScopeEntity::create([
      'name' => 'dynamic_scope',
    ])->save();
    Oauth2ScopeEntity::create([
      'name' => 'dynamic_scope:1',
    ])->save();

    $this->assertOauth2ScopeReferenceItems([
      'dynamic_scope',
      'dynamic_scope_1',
    ]);
  }

}
