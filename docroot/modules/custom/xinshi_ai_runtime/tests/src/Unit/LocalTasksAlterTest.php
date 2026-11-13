<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_runtime\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Tabs must not point at the usage routes that the off mode removes.
 *
 * @group xinshi_ai_runtime
 */
final class LocalTasksAlterTest extends UnitTestCase {

  private const TASKS = [
    'xinshi_ai.settings.tab' => ['route_name' => 'xinshi_ai.settings', 'base_route' => 'xinshi_ai.settings'],
    'xinshi_ai.models.tab' => ['route_name' => 'xinshi_ai.models.overview', 'base_route' => 'xinshi_ai.settings'],
    'xinshi_ai_usage.settings.tab' => ['route_name' => 'xinshi_ai_usage.settings', 'base_route' => 'xinshi_ai.settings'],
    'xinshi_ai_usage.price_book.tab' => ['route_name' => 'xinshi_ai_usage.price_book', 'base_route' => 'xinshi_ai.settings'],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once dirname(__DIR__, 3) . '/xinshi_ai_runtime.module';
  }

  public function testOffModeRemovesOnlyUsageTabs(): void {
    $this->assertSame(['xinshi_ai.settings.tab', 'xinshi_ai.models.tab'], array_keys($this->alter('off')));
  }

  public function testEnabledModesKeepEveryTab(): void {
    $this->assertSame(self::TASKS, $this->alter('observe'));
    $this->assertSame(self::TASKS, $this->alter('enforce'));
  }

  private function alter(string $mode): array {
    $container = new ContainerBuilder();
    $container->setParameter('xinshi_site_finance', ['metering' => $mode]);
    \Drupal::setContainer($container);
    $tasks = self::TASKS;
    xinshi_ai_runtime_local_tasks_alter($tasks);
    return $tasks;
  }

}
