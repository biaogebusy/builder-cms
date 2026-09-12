<?php

declare(strict_types=1);

namespace Drupal\Tests\acquia_connector\Unit;

use Drupal\acquia_connector\Services\AcquiaTelemetryService;
use Drupal\Component\Datetime\TimeInterface;
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
      $this->createMock(LoggerChannelFactoryInterface::class),
      $this->createMock(TimeInterface::class)
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

  /**
   * Tests the shouldSendTelemetryData() method via reflection.
   *
   * @throws \ReflectionException
   */
  public function testShouldSendTelemetryData(): void {
    $event_type = 'acquia_telemetry_event';
    $telemetry_data = ['event_type' => 'Test Event', 'user_id' => 'abc123', 'event_properties' => []];

    $stateMap = [];
    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturnCallback(function (string $key, $default = NULL) use (&$stateMap) {
      return $stateMap[$key] ?? $default;
    });
    $state->method('set')->willReturnCallback(function (string $key, $value) use (&$stateMap) {
      $stateMap[$key] = $value;
    });

    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(1000000);

    $sut = new AcquiaTelemetryService(
      $this->createMock(ModuleExtensionList::class),
      $this->createMock(ConfigFactoryInterface::class),
      $state,
      $this->createMock(LoggerChannelFactoryInterface::class),
      $time
    );

    $method = new \ReflectionMethod(AcquiaTelemetryService::class, 'shouldSendTelemetryData');

    $hashMethod = new \ReflectionMethod(AcquiaTelemetryService::class, 'getHash');

    $telemetryDataProp = new \ReflectionProperty(AcquiaTelemetryService::class, 'telemetryData');

    // No AH env set — non-Acquia environment should NOT send.
    putenv('AH_SITE_ENVIRONMENT=');
    self::assertFalse(
      $method->invoke($sut, $event_type, $telemetry_data),
      'Should not send telemetry data on non-Acquia environment.'
    );

    // Non-prod Acquia env (dev) should NOT send.
    putenv('AH_SITE_ENVIRONMENT=dev');
    self::assertFalse(
      $method->invoke($sut, $event_type, $telemetry_data),
      'Should not send telemetry data on Acquia non-prod (dev) environment.'
    );

    // Prod env with no prior state SHOULD send.
    putenv('AH_SITE_ENVIRONMENT=prod');
    self::assertTrue(
      $method->invoke($sut, $event_type, $telemetry_data),
      'Should send telemetry data on Acquia prod environment with no prior state.'
    );

    // Should NOT send if data sent within the last 24 hours.
    $stateMap["acquia_connector.telemetry.$event_type.timestamp"] = 1000000 - 3600;
    self::assertFalse(
      $method->invoke($sut, $event_type, $telemetry_data),
      'Should not send telemetry data if sent within last 24 hours.'
    );

    // SHOULD send if last send was more than 24 hours ago.
    $stateMap["acquia_connector.telemetry.$event_type.timestamp"] = 1000000 - 86401;
    self::assertTrue(
      $method->invoke($sut, $event_type, $telemetry_data),
      'Should send telemetry data if last sent more than 24 hours ago.'
    );

    // Should NOT send if hash matches (data unchanged).
    $currentHash = $hashMethod->invoke($sut, $telemetry_data);
    $stateMap["acquia_connector.telemetry.$event_type.hash"] = $currentHash;
    self::assertFalse(
      $method->invoke($sut, $event_type, $telemetry_data),
      'Should not send telemetry data if current data hash matches stored hash.'
    );

    // SHOULD send if hash differs (data changed).
    $stateMap["acquia_connector.telemetry.$event_type.hash"] = 'different_hash_value_xyz';
    self::assertTrue(
      $method->invoke($sut, $event_type, $telemetry_data),
      'Should send telemetry data if current data hash differs from stored hash.'
    );

    // Should NOT send if already sent in this request (in-memory cache).
    $telemetryDataProp->setValue($sut, [$event_type => $telemetry_data]);
    self::assertFalse(
      $method->invoke($sut, $event_type, $telemetry_data),
      'Should not send telemetry data if already sent in this request.'
    );

    // *live pattern env SHOULD send (reset cache first).
    $telemetryDataProp->setValue($sut, []);
    $stateMap["acquia_connector.telemetry.$event_type.hash"] = 'different_hash_value_xyz';
    $stateMap["acquia_connector.telemetry.$event_type.timestamp"] = 1000000 - 86401;
    putenv('AH_SITE_ENVIRONMENT=01live');
    self::assertTrue(
      $method->invoke($sut, $event_type, $telemetry_data),
      'Should send telemetry data on *live pattern Acquia environment.'
    );

    // Restore env.
    putenv('AH_SITE_ENVIRONMENT=');
  }

}
