<?php

namespace Drupal\Tests\simple_oauth\Unit\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\simple_oauth\Authentication\Provider\SimpleOauthAuthenticationProvider;
use Drupal\simple_oauth\PageCache\DisallowSimpleOauthRequests;
use Drupal\simple_oauth\PageCache\SimpleOauthRequestPolicyInterface;
use Drupal\simple_oauth\Server\ResourceServerInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\simple_oauth\Authentication\Provider\SimpleOauthAuthenticationProvider
 * @group simple_oauth
 */
class SimpleOauthAuthenticationTest extends UnitTestCase {

  use ProphecyTrait;
  /**
   * The authentication provider.
   *
   * @var \Drupal\Core\Authentication\AuthenticationProviderInterface
   */
  protected AuthenticationProviderInterface $provider;

  /**
   * The OAuth page cache request policy.
   *
   * @var \Drupal\simple_oauth\PageCache\SimpleOauthRequestPolicyInterface
   */
  protected SimpleOauthRequestPolicyInterface $oauthPageCacheRequestPolicy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $resource_server = $this->prophesize(ResourceServerInterface::class);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->oauthPageCacheRequestPolicy = new DisallowSimpleOauthRequests();
    $this->provider = new SimpleOauthAuthenticationProvider(
      $resource_server->reveal(),
      $entity_type_manager->reveal(),
      $this->oauthPageCacheRequestPolicy
    );
  }

  /**
   * @covers ::applies
   *
   * @dataProvider hasTokenValueProvider
   */
  public function testHasTokenValue(?string $authorization, bool $has_token): void {
    $request = new Request();

    if ($authorization !== NULL) {
      $request->headers->set('Authorization', $authorization);
    }

    $this->assertSame($has_token, $this->provider->applies($request));
    $this->assertSame(
      $has_token ? RequestPolicyInterface::DENY : NULL,
      $this->oauthPageCacheRequestPolicy->check($request)
    );
  }

  /**
   * Data provider for ::testHasTokenValue.
   */
  public function hasTokenValueProvider(): array {
    $token = $this->randomMachineName();
    $data = [];

    // 1. Authentication header.
    $data[] = ['Bearer ' . $token, TRUE];
    // 2. Authentication header. Trailing white spaces.
    $data[] = ['  Bearer ' . $token, TRUE];
    // 3. Authentication header. No white spaces.
    $data[] = ['Foo' . $token, FALSE];
    // 4. Authentication header. Empty value.
    $data[] = ['', FALSE];
    // 5. Authentication header. Fail: no token.
    $data[] = [NULL, FALSE];

    return $data;
  }

}
