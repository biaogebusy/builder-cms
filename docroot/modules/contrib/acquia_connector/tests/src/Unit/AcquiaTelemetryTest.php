<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Unit;

use Drupal\acquia_connector\Services\AcquiaTelemetryService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for Acquia Telemetry Classes.
 *
 * @group acquia_connector
 */
final class AcquiaTelemetryTest extends UnitTestCase {

  /**
   * Tests the filtered module names for Acquia extensions.
   */
  public function testGetAcquiaExtensionNames() {
    $modules = [
      'token',
      'acquia_connector',
      'acquia_perz',
      'acquia_cms_page',
      'cohesion',
      'acquia_cms_toolbar',
      'media_acquiadam',
    ];
    sort($modules);
    $module_list = $this->createMock(ModuleExtensionList::class);
    $module_list->method('getAllAvailableInfo')->willReturn(array_combine($modules, $modules));

    $sut = new AcquiaTelemetryService(
      $module_list,
      $this->createMock(ConfigFactoryInterface::class),
      $this->createMock(StateInterface::class),
      $this->createMock(LoggerChannelFactoryInterface::class)
    );
    self::assertEquals(
      [
        'acquia_cms_page',
        'acquia_cms_toolbar',
        'acquia_connector',
        'acquia_perz',
        'cohesion',
        'media_acquiadam',
      ],
      $sut->getAcquiaExtensionNames()
    );
  }

}
