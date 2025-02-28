<?php

namespace Drupal\Tests\blazy\Unit;

use Drupal\blazy\BlazyManager;
use Drupal\Tests\blazy\Traits\BlazyManagerUnitTestTrait;
use Drupal\Tests\blazy\Traits\BlazyUnitTestTrait;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests the Blazy manager base.
 *
 * @coversDefaultClass \Drupal\blazy\BlazyManagerBase
 * @group blazy
 */
class BlazyManagerBaseUnitTest extends UnitTestCase {

  use BlazyUnitTestTrait;
  use BlazyManagerUnitTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setUpUnitServices();
  }

  /**
   * @covers ::create
   * @covers ::__construct
   */
  public function testBlazyManagerCreate() {
    $container = $this->createMock(ContainerInterface::class);
    $exception = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;

    $map = [
      ['entity.repository', $exception, $this->entityRepository],
      ['entity_type.manager', $exception, $this->entityTypeManager],
      ['module_handler', $exception, $this->moduleHandler],
      ['renderer', $exception, $this->renderer],
      ['config.factory', $exception, $this->configFactory],
      ['cache.default', $exception, $this->cache],
      ['language_manager', $exception, $this->languageManager],
    ];

    $container->expects($this->any())
      ->method('get')
      ->willReturnMap($map);

    $blazyManager = BlazyManager::create($container);
    $this->assertInstanceOf(BlazyManager::class, $blazyManager);
  }

}
