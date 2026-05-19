<?php

namespace Drupal\Tests\simple_oauth\Unit\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Drupal\Core\Session\PermissionCheckerInterface;
use Drupal\TestTools\Random;
use Drupal\Tests\UnitTestCase;
use Drupal\simple_oauth\Authentication\Provider\SimpleOauthAuthenticationProvider;
use Drupal\simple_oauth\PageCache\DisallowSimpleOauthRequests;
use Drupal\simple_oauth\PageCache\SimpleOauthRequestPolicyInterface;
use Drupal\simple_oauth\Server\ResourceServerFactoryInterface;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Routing\Route;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

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
   * The path validator mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $pathValidatorMock;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected PathValidatorInterface $pathValidator;

  /**
   * The route provider mock.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $routeProviderMock;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected RouteProviderInterface $routeProvider;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $resource_server_factory = $this->prophesize(ResourceServerFactoryInterface::class);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->oauthPageCacheRequestPolicy = new DisallowSimpleOauthRequests();
    $http_message_factory = $this->prophesize(HttpMessageFactoryInterface::class);
    $http_foundation_factory = $this->prophesize(HttpFoundationFactoryInterface::class);

    // Store the Prophecy mock and reveal it for the strongly typed property.
    $this->pathValidatorMock = $this->prophesize(PathValidatorInterface::class);
    $this->pathValidator = $this->pathValidatorMock->reveal();
    $this->routeProviderMock = $this->prophesize(RouteProviderInterface::class);
    $this->routeProvider = $this->routeProviderMock->reveal();
    $request_stack = $this->prophesize(RequestStack::class);
    $permission_checker = $this->prophesize(PermissionCheckerInterface::class);

    $this->provider = new SimpleOauthAuthenticationProvider(
      $resource_server_factory->reveal(),
      $entity_type_manager->reveal(),
      $this->oauthPageCacheRequestPolicy,
      $http_message_factory->reveal(),
      $http_foundation_factory->reveal(),
      $this->pathValidator,
      $this->routeProvider,
      $request_stack->reveal(),
      $permission_checker->reveal()
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
  public static function hasTokenValueProvider(): array {
    $token = Random::string();
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

  /**
   * @covers ::applies
   */
  public function testRouteOptOut(): void {
    // Create a request with a Bearer token.
    $request = new Request();
    $request->headers->set('Authorization', 'Bearer token123');
    $request->attributes->set('_route', 'test_route');

    // Mock the pathValidator to return a valid URL object with a route name.
    $url_object = $this->prophesize(Url::class);
    $url_object->getRouteName()->willReturn('test_route');
    $this->pathValidatorMock->getUrlIfValidWithoutAccessCheck($request->getPathInfo())
      ->willReturn($url_object->reveal());

    // Test with no route (should apply OAuth).
    $this->routeProviderMock->getRouteByName('test_route')->willReturn(NULL);
    $this->assertTrue($this->provider->applies($request));

    // Test with a route that doesn't have the opt-out (should apply OAuth)
    $route_without_option = new Route('/test/route');
    $this->routeProviderMock->getRouteByName('test_route')->willReturn($route_without_option);
    $this->assertTrue($this->provider->applies($request));

    // Test with a route that has the opt-out option (should NOT apply OAuth)
    $route_with_option = new Route('/test/route', [], [], ['_oauth_skip_auth' => TRUE]);
    $this->routeProviderMock->getRouteByName('test_route')->willReturn($route_with_option);
    $this->assertFalse($this->provider->applies($request));

    // Test with an invalid path (should apply OAuth)
    $this->pathValidatorMock->getUrlIfValidWithoutAccessCheck($request->getPathInfo())
      ->willReturn(NULL);
    $this->assertTrue($this->provider->applies($request));
  }

  /**
   * Test that no route lookups occur when handling a non-OAuth request.
   */
  public function testNoRouteLookup(): void {
    $request = new Request();
    $this->provider->applies($request);
    $this->pathValidatorMock->getUrlIfValidWithoutAccessCheck(Argument::any())->shouldNotBeCalled();
    $this->routeProviderMock->getRouteByName(Argument::any())->shouldNotBeCalled();
  }

}
