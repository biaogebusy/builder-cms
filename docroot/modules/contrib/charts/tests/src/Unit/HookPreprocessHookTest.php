<?php

declare(strict_types=1);

namespace Drupal\Tests\charts\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\DependencyInjection\ContainerBuilder;

require_once __DIR__ . '/../../../charts.module';

/**
 * Tests template_preprocess_charts_chart.
 *
 * @group charts
 */
class HookPreprocessHookTest extends UnitTestCase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $container->set('string_translation', $string_translation);

    $this->configFactory = $this->createMock('Drupal\Core\Config\ConfigFactoryInterface');
    $container->set('config.factory', $this->configFactory);

    \Drupal::setContainer($container);
  }

  /**
   * Tests template_preprocess_charts_chart().
   */
  public function testTemplatePreprocess() {
    $settings = $this->createMock('Drupal\Core\Config\ImmutableConfig');
    $settings->expects($this->once())
      ->method('get')
      ->with('advanced.debug')
      ->willReturn(TRUE);
    $this->configFactory->expects($this->once())
      ->method('get')
      ->with('charts.settings')
      ->willReturn($settings);
    $variables = [
      'element' => [
        '#attributes' => [
          'id' => 'test-chart',
          'class' => ['chart'],
        ],
        '#id' => 'test-chart',
        '#chart' => 'chart data',
        '#content_prefix' => '<div class="prefix">Prefix</div>',
        '#content_suffix' => '<div class="suffix">Suffix</div>',
      ],
    ];
    template_preprocess_charts_chart($variables);

    $this->assertArrayHasKey('content', $variables);
    $this->assertArrayHasKey('content_prefix', $variables);
    $this->assertArrayHasKey('content_suffix', $variables);
    $this->assertArrayHasKey('debug', $variables);
    $this->assertArrayHasKey('json', $variables['debug']);
  }

}
