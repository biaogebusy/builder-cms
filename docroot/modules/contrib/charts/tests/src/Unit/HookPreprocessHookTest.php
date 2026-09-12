<?php

declare(strict_types=1);

namespace Drupal\Tests\charts\Unit;

use Drupal\charts\Hook\ChartsHooks;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Utility\Token;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RequestStack;

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

    $container = new ContainerBuilder();

    $string_translation = $this->getStringTranslationStub();
    $container->set('string_translation', $string_translation);

    $table_builder = $this->createMock('Drupal\charts\Service\ChartTableBuilder');
    $container->set('charts.table_builder', $table_builder);

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $container->set('config.factory', $this->configFactory);

    // ChartHooks dependencies.
    $requestStack = $this->createMock(RequestStack::class);
    $extensionPathResolver = $this->createMock(ExtensionPathResolver::class);
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $token = $this->createMock(Token::class);

    // Instantiate the ChartHooks class with the shared configFactory mock.
    $this->chartsHooks = new ChartsHooks(
      $requestStack,
      $this->configFactory,
      $extensionPathResolver,
      $moduleHandler,
      $token
    );

    $container->set('charts.hooks', $this->chartsHooks);

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

    $this->chartsHooks->templatePreprocessChartsChart($variables);

    $this->assertArrayHasKey('content_prefix', $variables);
    $this->assertArrayHasKey('content_suffix', $variables);
    $this->assertArrayHasKey('debug', $variables);
    $this->assertArrayHasKey('json', $variables['debug']);
  }

}
