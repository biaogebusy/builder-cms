<?php

declare(strict_types=1);

namespace Drupal\Tests\charts_highcharts\Unit\Plugin\chart\Library;

use Drupal\charts_highcharts\Plugin\chart\Library\Highcharts;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormStateInterface;
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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

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

    // By default the plugin resolves no library configuration.
    $this->configFactory = $this->buildConfigFactory([], []);
    $container->set('config.factory', $this->configFactory);

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
      $this->configFactory,
    );

    $this->assertInstanceOf(Highcharts::class, $plugin);
  }

  /**
   * Type-providing libraries gate their chart types; feature libraries do not.
   *
   * @param array $library_config
   *   The Highcharts library configuration to resolve.
   * @param string[] $expected_present
   *   Chart types that must remain in the supported list.
   * @param string[] $expected_absent
   *   Chart types that must be filtered out of the supported list.
   *
   * @dataProvider providerSupportedChartTypes
   *
   * @covers ::getSupportedChartTypes
   * @covers ::getUnsupportedChartTypes
   */
  public function testGetSupportedChartTypes(array $library_config, array $expected_present, array $expected_absent): void {
    $plugin = $this->createPluginWithLibraryConfig($library_config);

    $supported = $plugin->getSupportedChartTypes();

    foreach ($expected_present as $type) {
      $this->assertContains($type, $supported);
    }
    foreach ($expected_absent as $type) {
      $this->assertNotContains($type, $supported);
    }
    // The list is always re-indexed after filtering.
    $this->assertSame(array_values($supported), $supported);
  }

  /**
   * Provides test cases for ::testGetSupportedChartTypes().
   *
   * @return array[]
   *   A set of test cases keyed by a human-readable description.
   */
  public static function providerSupportedChartTypes(): array {
    $all_type_libraries = [
      'dumbbell_library' => TRUE,
      'heatmap_library' => TRUE,
      'solidgauge_library' => TRUE,
      'treemap_library' => TRUE,
    ];

    return [
      'disabled type libraries drop their types' => [
        ['solidgauge_library' => TRUE],
        ['line', 'pie', 'solidgauge'],
        ['heatmap', 'dumbbell', 'treemap'],
      ],
      'enabled type libraries keep their types' => [
        $all_type_libraries,
        ['dumbbell', 'heatmap', 'solidgauge', 'treemap'],
        [],
      ],
      'feature libraries never gate a type' => [
        ['coloraxis_library' => FALSE, 'pareto_library' => FALSE] + $all_type_libraries,
        ['bar', 'column', 'pie', 'donut', 'treemap'],
        [],
      ],
    ];
  }

  /**
   * The library toggle is resolved from the per-library config bucket.
   *
   * The lookup must read charts_default_settings.library_configs keyed by the
   * plugin id, not the default library's charts_default_settings.library_config
   * (which may belong to a different library entirely).
   *
   * @covers ::isSupportedChartType
   * @covers ::optionalLibraryEnabled
   */
  public function testLibraryToggleResolvesFromPerLibraryConfig(): void {
    // The default library_config belongs to another library and must be
    // ignored; Highcharts settings live under library_configs['highcharts'].
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['charts_default_settings.library_configs', ['highcharts' => ['heatmap_library' => TRUE]]],
      ['charts_default_settings.library_config', ['heatmap_library' => FALSE]],
    ]);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('charts.settings')->willReturn($config);

    $plugin = $this->buildPlugin($config_factory);

    $this->assertTrue($plugin->isSupportedChartType('heatmap'));
    $this->assertTrue($plugin->isSupportedChartType('line'));
  }

  /**
   * Color Axis options are gated behind the Color Axis feature library.
   *
   * When disabled the options are removed, but the pie type stays valid and the
   * options fieldset is not force-hidden (no '#access' => FALSE).
   *
   * @covers ::addBaseSettingsElementOptions
   * @covers ::featureLibraries
   */
  public function testColorAxisOptionsGatedByFeatureLibrary(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $complete_form = [];

    $plugin = $this->createPluginWithLibraryConfig(['coloraxis_library' => FALSE]);
    $element = ['#chart_type' => 'pie'];
    $plugin->addBaseSettingsElementOptions($element, [], $form_state, $complete_form);
    $this->assertArrayNotHasKey('coloraxis', $element);
    $this->assertArrayNotHasKey('min_color', $element);
    $this->assertArrayNotHasKey('max_color', $element);
    $this->assertArrayNotHasKey('#access', $element);
    // The fieldset stays visible with an explanatory note instead of hiding.
    $this->assertArrayHasKey('empty_message', $element);

    $plugin = $this->createPluginWithLibraryConfig(['coloraxis_library' => TRUE]);
    $element = ['#chart_type' => 'pie'];
    $plugin->addBaseSettingsElementOptions($element, [], $form_state, $complete_form);
    $this->assertArrayHasKey('coloraxis', $element);
    $this->assertArrayHasKey('min_color', $element);
    $this->assertArrayHasKey('max_color', $element);
    $this->assertArrayNotHasKey('empty_message', $element);
  }

  /**
   * Pareto options are gated behind the Pareto feature library.
   *
   * The always-available stackLabels option and the fieldset remain when Pareto
   * is disabled.
   *
   * @covers ::addBaseSettingsElementOptions
   * @covers ::featureLibraries
   */
  public function testParetoOptionsGatedByFeatureLibrary(): void {
    $form_state = $this->createMock(FormStateInterface::class);
    $complete_form = [];

    $plugin = $this->createPluginWithLibraryConfig(['pareto_library' => FALSE]);
    $element = ['#chart_type' => 'column'];
    $plugin->addBaseSettingsElementOptions($element, [], $form_state, $complete_form);
    $this->assertArrayHasKey('enable_stacklabels', $element);
    $this->assertArrayNotHasKey('pareto_line', $element);
    $this->assertArrayNotHasKey('pareto_color', $element);
    // The fieldset is not empty (stackLabels remains), so no note is added.
    $this->assertArrayNotHasKey('empty_message', $element);

    $plugin = $this->createPluginWithLibraryConfig(['pareto_library' => TRUE]);
    $element = ['#chart_type' => 'column'];
    $plugin->addBaseSettingsElementOptions($element, [], $form_state, $complete_form);
    $this->assertArrayHasKey('pareto_line', $element);
    $this->assertArrayHasKey('pareto_color', $element);
  }

  /**
   * Builds a config factory mock returning the given charts.settings values.
   *
   * @param array $library_configs
   *   The charts_default_settings.library_configs value.
   * @param array $library_config
   *   The charts_default_settings.library_config value.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The config factory mock.
   */
  protected function buildConfigFactory(array $library_configs, array $library_config) {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['charts_default_settings.library_configs', $library_configs],
      ['charts_default_settings.library_config', $library_config],
    ]);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('charts.settings')->willReturn($config);

    return $config_factory;
  }

  /**
   * Builds a Highcharts plugin (with a full type list) using a config factory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory to inject.
   *
   * @return \Drupal\charts_highcharts\Plugin\chart\Library\Highcharts
   *   The plugin instance.
   */
  protected function buildPlugin(ConfigFactoryInterface $config_factory): Highcharts {
    return new Highcharts(
      [],
      'highcharts',
      [
        'id' => 'highcharts',
        'label' => 'Highcharts',
        'provider' => 'charts_highcharts',
        'types' => [
          'area',
          'bar',
          'column',
          'donut',
          'dumbbell',
          'heatmap',
          'line',
          'pie',
          'scatter',
          'solidgauge',
          'treemap',
        ],
      ],
      $this->elementInfo,
      $this->pluginManagerChartsType,
      $this->formBuilder,
      $this->moduleHandler,
      $config_factory,
    );
  }

  /**
   * Builds a Highcharts plugin whose per-library config is the given array.
   *
   * @param array $highcharts_config
   *   The library configuration stored for the Highcharts plugin.
   *
   * @return \Drupal\charts_highcharts\Plugin\chart\Library\Highcharts
   *   The plugin instance.
   */
  protected function createPluginWithLibraryConfig(array $highcharts_config): Highcharts {
    $config_factory = $this->buildConfigFactory(['highcharts' => $highcharts_config], []);
    return $this->buildPlugin($config_factory);
  }

}
