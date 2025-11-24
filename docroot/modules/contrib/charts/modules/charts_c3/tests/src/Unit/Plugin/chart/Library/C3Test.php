<?php

declare(strict_types=1);

namespace Drupal\Tests\charts_c3\Unit\Plugin\chart\Library;

use Drupal\charts_c3\Plugin\chart\Library\C3;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the ChartsConfig Form class.
 *
 * @group charts
 * @coversDefaultClass \Drupal\charts_c3\Plugin\chart\Library\C3
 * @use \Drupal\charts\Plugin\chart\Library\ChartBase
 */
class C3Test extends UnitTestCase {

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
   * The C3 plugin.
   *
   * @var \Drupal\charts_c3\Plugin\chart\Library\C3
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

    $this->plugin = C3::create($container, [], 'billboard', [
      'id' => 'billboard',
      'label' => 'C3',
      'provider' => 'charts_c3',
    ]);

  }

  /**
   * Tests the creation of the C3 plugin.
   *
   * @covers ::create
   */
  public function testCreate(): void {
    $container = \Drupal::getContainer();
    $plugin = C3::create($container, [], 'billboard', [
      'id' => 'billboard',
      'label' => 'C3',
      'provider' => 'charts_c3',
    ]);
    $this->assertInstanceOf(C3::class, $plugin);
  }

  /**
   * Tests the constructor of the C3 plugin.
   *
   * @covers ::__construct
   */
  public function testConstruct(): void {
    $plugin = new C3(
      [],
      'billboard',
      [
        'id' => 'billboard',
        'label' => 'C3',
        'provider' => 'charts_c3',
      ],
      $this->elementInfo,
      $this->moduleHandler,
    );

    $this->assertInstanceOf(C3::class, $plugin);
  }

}
