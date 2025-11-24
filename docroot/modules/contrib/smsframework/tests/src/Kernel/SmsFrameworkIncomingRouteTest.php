<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Kernel;

use Drupal\Core\Routing\RouteProviderInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Tests incoming routes for gateway plugins.
 *
 * @group SMS Framework
 */
final class SmsFrameworkIncomingRouteTest extends SmsFrameworkKernelBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'sms', 'entity_test', 'user', 'field', 'telephone',
    'dynamic_entity_reference', 'sms_test_gateway', 'basic_auth',
  ];

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  private RouteProviderInterface $routeProvider;

  /**
   * An incoming gateway instance.
   *
   * @var \Drupal\sms\Entity\SmsGatewayInterface
   */
  private RouteProviderInterface $incomingGateway;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->routeProvider = $this->container->get('router.route_provider');
  }

  /**
   * Tests route does not exist for gateway without incoming route.
   */
  public function testIncomingRouteUnsupported(): void {
    $gateway = $this->createMemoryGateway(['plugin' => 'capabilities_default']);
    $this->expectException(RouteNotFoundException::class);
    $route = 'sms.incoming.receive.' . $gateway->id();
    $this->routeProvider->getRouteByName($route);
  }

  /**
   * Tests route exists for gateway with incoming route annotation.
   */
  public function testIncomingRoute(): void {
    $incoming_gateway = $this->createMemoryGateway(['plugin' => 'incoming']);
    $name = 'sms.incoming.receive.' . $incoming_gateway->id();
    $route = $this->routeProvider->getRouteByName($name);
    static::assertEquals(
      $incoming_gateway->getPushIncomingPath(),
      $route->getPath(),
    );
  }

}
