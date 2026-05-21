<?php

declare(strict_types=1);

namespace Drupal\Tests\simple_oauth\Unit;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\simple_oauth\Authentication\TokenAuthUserInterface;
use Drupal\simple_oauth\Entity\Oauth2TokenInterface;
use Drupal\simple_oauth\Cache\Oauth2ScopeCacheContext;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\simple_oauth\Plugin\Field\FieldType\Oauth2ScopeReferenceItemListInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\simple_oauth\Cache\Oauth2ScopeCacheContext
 * @group Cache
 */
class Oauth2ScopeCacheContextTest extends UnitTestCase {

  /**
   * @covers ::getContext
   */
  public function testCalculatedScope(): void {
    $account = $this->prophesize(AccountProxyInterface::class);
    $token_auth_user = $this->prophesize(TokenAuthUserInterface::class);
    $token = $this->prophesize(Oauth2TokenInterface::class);
    $scopes_field = $this->prophesize(Oauth2ScopeReferenceItemListInterface::class);

    $scopes = [];
    foreach (['scope1', 'scope2'] as $scope_name) {
      $scope = $this->prophesize(Oauth2ScopeInterface::class);
      $scope->getName()->willReturn($scope_name);
      $scopes[] = $scope->reveal();
    }

    $scopes_field->getScopes()->willReturn($scopes);
    $token->get('scopes')->willReturn($scopes_field->reveal());
    $token_auth_user->getToken()->willReturn($token->reveal());
    $account->getAccount()->willReturn($token_auth_user->reveal());

    $account->id()->willReturn(2);
    $cache_context = new Oauth2ScopeCacheContext($account->reveal());
    $this->assertSame('true', $cache_context->getContext('scope1'));
    $this->assertSame('true', $cache_context->getContext('scope2'));
    $this->assertSame('false', $cache_context->getContext('scope3'));
    $this->assertSame('scope1,scope2', $cache_context->getContext());
  }

}
