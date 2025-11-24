<?php

declare(strict_types=1);

namespace Drupal\Tests\charts_highcharts\Unit\Plugin\chart\Library;

use Drupal\charts_highcharts\Plugin\chart\Library\Highcharts;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the ChartsConfig Form class.
 *
 * @group charts
 * @coversDefaultClass \Drupal\charts_highcharts\Plugin\chart\Library\Highcharts
 * @use \Drupal\charts\Plugin\chart\Library\ChartBase
 */
class HighchartsTest extends UnitTestCase {

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
   * The plugin manager for chart types.
   *
   * @var \Drupal\charts\Plugin\chart\ChartTypePluginManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $pluginManagerChartsType;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $formBuilder;

  /**
   * The Highcharts plugin.
   *
   * @var \Drupal\charts_highcharts\Plugin\chart\Library\Highcharts
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

    $this->pluginManagerChartsType = $this->createMock('Drupal\charts\TypeManager');
    $container->set('plugin.manager.charts_type', $this->pluginManagerChartsType);

    $this->formBuilder = $this->createMock('Drupal\Core\Form\FormBuilderInterface');
    $container->set('form_builder', $this->formBuilder);

    \Drupal::setContainer($container);

    $this->plugin = Highcharts::create($container, [], 'highcharts', [
      'id' => 'highcharts',
      'label' => 'Highcharts',
      'provider' => 'charts_highcharts',
    ]);

  }

  /**
   * Tests the creation of the Highcharts plugin.
   *
   * @covers ::create
   */
  public function testCreate(): void {
    $container = \Drupal::getContainer();
    $plugin = Highcharts::create($container, [], 'highcharts', [
      'id' => 'highcharts',
      'label' => 'Highcharts',
      'provider' => 'charts_highcharts',
    ]);
    $this->assertInstanceOf(Highcharts::class, $plugin);
  }

  /**
   * Tests the constructor of the Highcharts plugin.
   *
   * @covers ::__construct
   */
  public function testConstruct(): void {
    $plugin = new Highcharts(
      [],
      'highcharts',
      [
        'id' => 'highcharts',
        'label' => 'Highcharts',
        'provider' => 'charts_highcharts',
      ],
      $this->elementInfo,
      $this->pluginManagerChartsType,
      $this->formBuilder,
      $this->moduleHandler,
    );

    $this->assertInstanceOf(Highcharts::class, $plugin);
  }

}
