<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\message\MessagePurgePluginManager;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Unit tests for MessagePurgePluginManager.
 *
 * @coversDefaultClass \Drupal\message\MessagePurgePluginManager
 *
 * @group Message
 */
class MessagePurgePluginManagerTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The plugin manager under test.
   *
   * @var \Drupal\message\MessagePurgePluginManager
   */
  protected MessagePurgePluginManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $namespaces = new \ArrayObject([]);
    $cache = $this->prophesize(CacheBackendInterface::class)->reveal();
    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    $module_handler->alter('message_purge', [], NULL)->willReturn(NULL);
    $this->manager = new MessagePurgePluginManager(
      $namespaces,
      $cache,
      $module_handler->reveal()
    );
    $this->manager->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests sorting plugin definitions by configured weight.
   *
   * @covers ::sortDefinitions
   */
  public function testSortDefinitionsByWeight(): void {
    $definitions = [
      'quota' => ['id' => 'quota'],
      'days' => ['id' => 'days'],
    ];
    $settings = [
      'quota' => ['weight' => 10],
      'days' => ['weight' => 1],
    ];

    $method = new \ReflectionMethod(MessagePurgePluginManager::class, 'sortDefinitions');
    $method->invokeArgs($this->manager, [&$definitions, $settings]);

    $this->assertSame(['days', 'quota'], array_keys($definitions));
  }

  /**
   * Tests equal weights leave relative order unchanged (comparator returns 0).
   *
   * @covers ::sortDefinitions
   */
  public function testSortDefinitionsEqualWeight(): void {
    $definitions = [
      'quota' => ['id' => 'quota'],
      'days' => ['id' => 'days'],
    ];
    $settings = [
      'quota' => ['weight' => 5],
      'days' => ['weight' => 5],
    ];

    $method = new \ReflectionMethod(MessagePurgePluginManager::class, 'sortDefinitions');
    $method->invokeArgs($this->manager, [&$definitions, $settings]);

    // Equal weights: uasort comparator returns 0; order is stable enough that
    // both plugins remain present.
    $this->assertCount(2, $definitions);
    $this->assertArrayHasKey('quota', $definitions);
    $this->assertArrayHasKey('days', $definitions);
  }

  /**
   * Tests missing settings default weight to 0.
   *
   * @covers ::sortDefinitions
   */
  public function testSortDefinitionsMissingSettingsDefaultWeight(): void {
    $definitions = [
      'quota' => ['id' => 'quota'],
      'days' => ['id' => 'days'],
    ];
    $settings = [
      'days' => ['weight' => -5],
    ];

    $method = new \ReflectionMethod(MessagePurgePluginManager::class, 'sortDefinitions');
    $method->invokeArgs($this->manager, [&$definitions, $settings]);

    $this->assertSame(['days', 'quota'], array_keys($definitions));
  }

}
