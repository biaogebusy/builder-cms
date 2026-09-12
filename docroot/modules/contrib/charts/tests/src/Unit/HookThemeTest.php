<?php

declare(strict_types=1);

namespace Drupal\Tests\charts\Unit;

use Drupal\charts\Hook\ChartsHooks;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Utility\Token;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the ChartsHooks::theme() method.
 *
 * @coversDefaultClass \Drupal\charts\Hook\ChartsHooks
 * @group charts
 */
class HookThemeTest extends UnitTestCase {

  /**
   * The charts hooks service.
   *
   * @var \Drupal\charts\Hook\ChartsHooks
   */
  protected ChartsHooks $chartsHooks;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock the dependencies needed for the ChartsHooks constructor.
    $requestStack = $this->createMock(RequestStack::class);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $extensionPathResolver = $this->createMock(ExtensionPathResolver::class);
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $token = $this->createMock(Token::class);

    // Instantiate the class we want to test.
    $this->chartsHooks = new ChartsHooks(
      $requestStack,
      $configFactory,
      $extensionPathResolver,
      $moduleHandler,
      $token
    );

  }

  /**
   * Tests the structure of the data returned by theme().
   *
   * @covers ::theme
   */
  public function testTheme(): void {
    // Call the method directly on the object and check its output.
    $data = $this->chartsHooks->theme();

    $this->assertIsArray($data);
    $this->assertCount(1, $data);
    $this->assertArrayHasKey('charts_chart', $data);
    $this->assertCount(1, $data['charts_chart']);
    $this->assertArrayHasKey('render element', $data['charts_chart']);
    $this->assertEquals('element', $data['charts_chart']['render element']);
  }

}
