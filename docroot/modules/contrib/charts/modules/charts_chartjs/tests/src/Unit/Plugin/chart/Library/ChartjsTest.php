<?php

declare(strict_types=1);

namespace Drupal\Tests\charts_chartjs\Unit\Plugin\chart\Library;

use Drupal\charts_chartjs\Plugin\chart\Library\Chartjs;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the ChartsConfig Form class.
 *
 * @group charts
 * @coversDefaultClass \Drupal\charts_chartjs\Plugin\chart\Library\Chartjs
 * @use \Drupal\charts\Plugin\chart\Library\ChartBase
 */
class ChartjsTest extends UnitTestCase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $moduleHandler;

  /**
   * The Chartjs plugin.
   *
   * @var \Drupal\charts_chartjs\Plugin\chart\Library\Chartjs
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

    \Drupal::setContainer($container);

    $this->plugin = Chartjs::create($container, [], 'chartjs', [
      'id' => 'chartjs',
      'label' => 'Chart.js',
      'provider' => 'charts_chartjs',
    ]);

  }

  /**
   * Tests the creation of the Chartjs plugin.
   *
   * @covers ::create
   */
  public function testCreate(): void {
    $container = \Drupal::getContainer();
    $plugin = Chartjs::create($container, [], 'chartjs', [
      'id' => 'chartjs',
      'label' => 'Chart.js',
      'provider' => 'charts_chartjs',
    ]);
    $this->assertInstanceOf(Chartjs::class, $plugin);
  }

}
