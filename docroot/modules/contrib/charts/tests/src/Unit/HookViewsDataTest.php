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
 * Tests the ChartsHooks::viewsData() method.
 *
 * @coversDefaultClass \Drupal\charts\Hook\ChartsHooks
 * @group charts
 */
class HookViewsDataTest extends UnitTestCase {

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

    // Mock all the dependencies required by the ChartsHooks constructor.
    // We don't need to configure them because the viewsData() method doesn't
    // use them.
    $requestStack = $this->createMock(RequestStack::class);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $extensionPathResolver = $this->createMock(ExtensionPathResolver::class);
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $token = $this->createMock(Token::class);

    // Instantiate the class we want to test, passing in the mocks.
    $this->chartsHooks = new ChartsHooks(
      $requestStack,
      $configFactory,
      $extensionPathResolver,
      $moduleHandler,
      $token
    );

    // The viewsData() method uses $this->t(). The StringTranslationTrait
    // needs a translator. UnitTestCase provides a simple stub for this.
    $this->chartsHooks->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests the structure of the data returned by viewsData().
   *
   * @covers ::viewsData
   */
  public function testViewsData(): void {
    // Call the method directly on the object and run assertions.
    $data = $this->chartsHooks->viewsData();

    $this->assertIsArray($data);
    $this->assertCount(1, $data, 'The data array should contain one top-level key.');
    $this->assertArrayHasKey('charts_fields', $data);

    $charts_fields = $data['charts_fields'];
    $this->assertCount(5, $charts_fields, 'The charts_fields array should contain 5 keys.');
    $this->assertArrayHasKey('table', $charts_fields);
    $this->assertArrayHasKey('field_charts_fields_scatter', $charts_fields);
    $this->assertArrayHasKey('field_charts_fields_bubble', $charts_fields);
    $this->assertArrayHasKey('field_charts_numeric_array', $charts_fields);
    $this->assertArrayHasKey('field_exposed_chart_type', $charts_fields);
  }

}
