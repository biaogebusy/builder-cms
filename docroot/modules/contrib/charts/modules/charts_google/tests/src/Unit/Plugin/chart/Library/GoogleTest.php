<?php

declare(strict_types=1);

namespace Drupal\Tests\charts_google\Unit\Plugin\chart\Library;

use Drupal\charts_google\Plugin\chart\Library\Google;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the ChartsConfig Form class.
 *
 * @group charts
 * @coversDefaultClass \Drupal\charts_google\Plugin\chart\Library\Google
 * @use \Drupal\charts\Plugin\chart\Library\ChartBase
 */
class GoogleTest extends UnitTestCase {

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
   * The Google plugin.
   *
   * @var \Drupal\charts_google\Plugin\chart\Library\Google
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

    \Drupal::setContainer($container);

    $this->plugin = Google::create($container, [], 'google', [
      'id' => 'google',
      'label' => 'Google',
      'provider' => 'charts_google',
    ]);

  }

  /**
   * Tests the creation of the Google plugin.
   *
   * @covers ::create
   */
  public function testCreate(): void {
    $container = \Drupal::getContainer();
    $plugin = Google::create($container, [], 'google', [
      'id' => 'google',
      'label' => 'Google',
      'provider' => 'charts_google',
    ]);
    $this->assertInstanceOf(Google::class, $plugin);
  }

  /**
   * Tests the constructor of the Google plugin.
   *
   * @covers ::__construct
   */
  public function testConstruct(): void {
    $plugin = new Google(
      [],
      'google',
      [
        'id' => 'google',
        'label' => 'Google',
        'provider' => 'charts_google',
      ],
      $this->moduleHandler,
      $this->elementInfo,
      $this->pluginManagerChartsType,
    );

    $this->assertInstanceOf(Google::class, $plugin);
  }

}
