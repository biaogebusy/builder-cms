<?php

declare(strict_types=1);

namespace Drupal\Tests\charts_billboard\Unit\Plugin\chart\Library;

use Drupal\charts_billboard\Plugin\chart\Library\Billboard;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the ChartsConfig Form class.
 *
 * @group charts
 * @coversDefaultClass \Drupal\charts_billboard\Plugin\chart\Library\Billboard
 * @use \Drupal\charts\Plugin\chart\Library\ChartBase
 */
class BillboardTest extends UnitTestCase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The element info manager.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $elementInfo;

  /**
   * The Billboard plugin.
   *
   * @var \Drupal\charts_billboard\Plugin\chart\Library\Billboard
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $container->set('string_translation', $string_translation);

    $this->moduleHandler = $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $container->set('module_handler', $this->moduleHandler);

    $this->elementInfo = $this->createMock('Drupal\Core\Render\ElementInfoManagerInterface');
    $container->set('element_info', $this->elementInfo);

    \Drupal::setContainer($container);

    $this->plugin = Billboard::create($container, [], 'billboard', [
      'id' => 'billboard',
      'label' => 'Billboard',
      'provider' => 'charts_billboard',
    ]);

  }

  /**
   * Tests the creation of the Billboard plugin.
   *
   * @covers ::create
   */
  public function testCreate(): void {
    $container = \Drupal::getContainer();
    $plugin = Billboard::create($container, [], 'billboard', [
      'id' => 'billboard',
      'label' => 'Billboard',
      'provider' => 'charts_billboard',
    ]);
    $this->assertInstanceOf(Billboard::class, $plugin);
  }

  /**
   * Tests the constructor of the Billboard plugin.
   *
   * @covers ::__construct
   */
  public function testConstruct(): void {
    $plugin = new Billboard(
      [],
      'billboard',
      [
        'id' => 'billboard',
        'label' => 'Billboard',
        'provider' => 'charts_billboard',
      ],
      $this->elementInfo,
      $this->moduleHandler,
    );

    $this->assertInstanceOf(Billboard::class, $plugin);
  }

}
