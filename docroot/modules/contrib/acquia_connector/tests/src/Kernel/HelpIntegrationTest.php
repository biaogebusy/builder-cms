<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Kernel;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\help\Plugin\Block\HelpBlock;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test the help messages from connector.
 *
 * @group acquia_connector
 */
final class HelpIntegrationTest extends AcquiaConnectorTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'help',
  ];

  /**
   * Tests render output from Help integration.
   */
  public function testHook(): void {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn('help.page.acquia_connector');
    $block = new HelpBlock(
      [],
      'help_block',
      [
        'provider' => 'help',
      ],
      Request::create('/'),
      $this->container->get('module_handler'),
      $route_match
    );
    $build = $block->build();
    $output = $this->container->get('renderer')->renderPlain($build);
    $this->setRawContent($output);
    $this->assertRaw('<h2>Acquia Connector</h2>');
    $this->assertLinkByHref('https://docs.acquia.com/cloud-platform/onboarding/install/');
  }

}
