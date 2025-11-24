<?php

namespace Drupal\Tests\acquia_connector\Functional;

use Drupal\acquia_connector\EventSubscriber\KernelTerminate\AcquiaTelemetry;
use Drupal\acquia_connector\Services\AcquiaTelemetryService;
use Drupal\Component\Uuid\Php as PhpUuid;
use Drupal\Core\Database\Database;
use Drupal\Tests\BrowserTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\KernelEvent;

/**
 * @coversDefaultClass \Drupal\acquia_connector\EventSubscriber\KernelTerminate\AcquiaTelemetry
 * @group acquia_connector
 */
final class AcquiaTelemetryTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dblog',
    'system',
    'user',
    'acquia_connector',
    'acquia_connector_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The telemetry Service.
   *
   * @var \Drupal\acquia_connector\Services\AcquiaTelemetryService
   */
  protected $acquiaTelemetry;

  /**
   * The Event Type machine name.
   *
   * @var string
   */
  protected $eventTypeMachine;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('system.site')
      ->set('uuid', (new PhpUuid())->generate())
      ->save();

    // Enable acquia_connector module.
    $this->container->get('module_installer')->install(['acquia_connector']);
    $this->resetAll();

    $this->acquiaTelemetry = new AcquiaTelemetryService(
      $this->container->get('extension.list.module'),
      $this->container->get('config.factory'),
      $this->container->get("state"),
      $this->container->get("logger.factory"),
    );
    $this->eventTypeMachine = preg_replace('@[^a-z0-9-]+@', '_', strtolower('Drupal Module Statistics'));
  }

  /**
   * Tests the sendTelemetry() method of AcquiaCmsTelemetry class for prod env.
   */
  public function testTelemetryLogs(): void {
    $count = 0;
    $telemetry_service = new AcquiaTelemetry(
      $this->container->get('acquia_connector.telemetry_service'),
    );
    $send_request = function () use ($telemetry_service) {
      $telemetry_service->onTerminateEvent(new KernelEvent(
        $this->container->get('http_kernel'),
        Request::create('/'),
        1
      ));
    };

    // Fake Acquia dev environment and then send data.
    putenv("AH_SITE_ENVIRONMENT=dev");
    // Invoke Acquia Temletry event.
    $send_request();
    $query_count = $this->getLogEntry();
    // Assert log entry does not exist.
    $this->assertSame($count, $query_count, 'Telemetry data should not be send as on Acquia dev environment.');

    // Fake Acquia stage environment and then send data.
    putenv("AH_SITE_ENVIRONMENT=stage");
    // Invoke Acquia Temletry event.
    $send_request();
    $query_count = $this->getLogEntry();
    // Assert log entry does not exist.
    $this->assertSame($count, $query_count, 'Telemetry data should not be send as on Acquia stage environment.');

    // Fake Acquia IDE environment and then send data.
    putenv("AH_SITE_ENVIRONMENT=IDE");
    // Invoke Acquia Temletry event.
    $send_request();
    $query_count = $this->getLogEntry();
    // Assert log entry does not exist.
    $this->assertSame($count, $query_count, 'Telemetry data should not be send as on Acquia IDE environment.');

    // Fake Acquia ODE environment and then send data.
    putenv("AH_SITE_ENVIRONMENT=ODE");
    // Invoke Acquia Temletry event.
    $send_request();
    $query_count = $this->getLogEntry();
    // Assert log entry does not exist.
    $this->assertSame($count, $query_count, 'Telemetry data should not be send as on Acquia ODE environment.');

    // Fake Acquia prod environment and then send data.
    putenv("AH_SITE_ENVIRONMENT=prod");
    // Invoke Acquia Temletry event.
    $send_request();
    $query_count = $this->getLogEntry();
    // Assert log entry does exist.
    $this->assertEquals(1, $query_count, 'Telemetry data should send as on Acquia prod environment.');

    // Check the log format is what we expect.
    $message = unserialize($this->getLogData()[0]->variables, ['allowed_classes' => FALSE]);
    $data = json_decode($message['@message'], TRUE);

    // Check acquia_connector exists and is enabled.
    $this->assertArrayHasKey('acquia_connector', $data['event_properties']['extensions']);
    $this->assertEquals('enabled', $data['event_properties']['extensions']['acquia_connector']['status']);

    // Check there are core modules.
    $this->assertNotEmpty($data['event_properties']['drupal']['core_enabled']);

    // Check there are profiles.
    $this->assertNotEmpty($data['event_properties']['profiles']);
  }

  /**
   * Tests the Telemetry sending log when module installed/uninstalled.
   */
  public function testTelemetryModuleInstallUninstallLogEntry(): void {
    // Fake Acquia prod environment and then send data.
    putenv("AH_SITE_ENVIRONMENT=prod");

    $this->container->get('module_installer')->install(['acquia_connector_subdata_test']);
    $query_count = $this->getLogEntry('acquia_extensions_installed');
    // Assert log entry exist.
    $this->assertEquals(1, $query_count, 'Telemetry data should send as on Acquia prod environment when module installed.');

    $this->container->get('module_installer')->uninstall(['acquia_connector_subdata_test']);
    $query_count = $this->getLogEntry('acquia_extensions_uninstalled');
    // Assert log entry exist.
    $this->assertEquals(1, $query_count, 'Telemetry data should send as on Acquia prod environment when module uninstalled.');

  }

  /**
   * Tests the telemetry data sent.
   */
  public function testTelemetry(): void {
    $get_telemetry_method = $this->getAcquiaTelemetryMethod("getTelemetryData");
    $actual_telemetry_data = $get_telemetry_method->invokeArgs($this->acquiaTelemetry, ['Drupal Module Statistics', []]);

    $this->assertArrayHasKey('event_type', $actual_telemetry_data, "Telemetry data does not contain event_type key.");
    $this->assertArrayHasKey('user_id', $actual_telemetry_data, "Telemetry data does not contain user_id key.");
    $this->assertNotEmpty($actual_telemetry_data['user_id'], "Site UUID is empty.");

    $this->assertArrayHasKey('event_properties', $actual_telemetry_data, "Telemetry data does not contain event_properties key.");

    $this->assertArrayHasKey('extensions', $actual_telemetry_data['event_properties'], "Telemetry data event_properties does not contain extensions key.");
    $this->assertArrayHasKey('php', $actual_telemetry_data['event_properties'], "Telemetry data event_properties does not contain php key.");
    $this->assertArrayHasKey('drupal', $actual_telemetry_data['event_properties'], "Telemetry data event_properties does not contain drupal key.");

    $this->assertContains('acquia_connector', array_keys($actual_telemetry_data['event_properties']['extensions']));
    $this->assertEquals(['version'], array_keys($actual_telemetry_data['event_properties']['php']));
    $this->assertEquals(PHP_VERSION, $actual_telemetry_data['event_properties']['php']['version']);
    $this->assertEquals(['version', 'core_enabled'], array_keys($actual_telemetry_data['event_properties']['drupal']));
    $this->assertContains('user', array_keys($actual_telemetry_data['event_properties']['drupal']['core_enabled']));

  }

  /**
   * Tests the shouldSendTelemetryData() method of AcquiaCmsTelemetry class.
   *
   * @throws \ReflectionException
   *
   * @dataProvider telemetryTestData
   */
  public function testIfTelemetryDataShouldSend($env, bool $should_send): void {
    $get_telemetry_method = $this->getAcquiaTelemetryMethod("getTelemetryData");
    $actual_telemetry_data = $get_telemetry_method->invokeArgs($this->acquiaTelemetry, [$this->eventTypeMachine, []]);
    $should_send_method = $this->getAcquiaTelemetryMethod("shouldSendTelemetryData");
    $env_check_method = $this->getAcquiaTelemetryMethod("isAcquiaProdEnv");
    $state_service = $this->container->get("state");
    $parameters = [
      $this->eventTypeMachine,
      [
        'extensions' => $actual_telemetry_data['event_properties']['extensions'],
        'php' => $actual_telemetry_data['event_properties']['php'],
        'drupal' => $actual_telemetry_data['event_properties']['drupal'],
      ],
    ];

    if ($env) {
      putenv("AH_SITE_ENVIRONMENT=$env");
    }
    $should_send_data = $env_check_method->invoke($this->acquiaTelemetry);
    $should_send_msg = !$should_send ? 'not' : '';
    $this->assertEquals($should_send, $should_send_data, "Should $should_send_msg send telemetry data.");

    $method_hash = $this->getAcquiaTelemetryMethod("getHash");
    $state_service->set(
      "acquia_connector.telemetry.$this->eventTypeMachine.hash",
      $method_hash->invoke($this->acquiaTelemetry, [
        'extensions' => $actual_telemetry_data['event_properties']['extensions'],
        'php' => $actual_telemetry_data['event_properties']['php'],
        'drupal' => $actual_telemetry_data['event_properties']['drupal'],
      ]),
    );

    if ($should_send) {
      $should_send_data = $should_send_method->invokeArgs($this->acquiaTelemetry, $parameters);
      $this->assertFalse($should_send_data, "Should not send telemetry data, if current telemetry data is same as data already sent.");

      $state_service->set("acquia_connector.telemetry.$this->eventTypeMachine.hash", 'O2X4mf9Csg8KLOIqNlUqc9dqXdsL_JE5hjKh4dRPemQ');
      $should_send_data = $should_send_method->invokeArgs($this->acquiaTelemetry, $parameters);
      $this->assertTrue($should_send_data, "Should send telemetry data, if current telemetry data has changed from data already sent.");
    }
  }

  /**
   * Data for testing embed codes.
   *
   * @return \Generator
   *   The test data.
   */
  public static function telemetryTestData(): \Generator {
    yield 'Non Acquia Environment' => [
      NULL,
      FALSE,
    ];
    yield 'Dev Acquia Environment' => [
      'dev',
      FALSE,
    ];
    yield 'Stage Acquia Environment' => [
      'stage',
      FALSE,
    ];
    yield 'Prod Acquia Environment' => [
      'prod',
      TRUE,
    ];
    yield 'IDE Acquia Environment' => [
      'IDE',
      FALSE,
    ];
    yield 'ODE Acquia Environment' => [
      'ODE',
      FALSE,
    ];
  }

  /**
   * Returns the AcquiaTelemetry ReflectionMethod object.
   *
   * @throws \ReflectionException
   */
  protected function getAcquiaTelemetryMethod(string $method_name): \ReflectionMethod {
    $class = new \ReflectionClass($this->acquiaTelemetry);
    $method = $class->getMethod($method_name);
    $method->setAccessible(TRUE);
    return $method;
  }

  /**
   * Get database log for Drupal Module Statistics entry.
   *
   * @param string $event_type
   *   The event type.
   *
   * @return array
   *   Array of log entries.
   */
  protected function getLogData(string $event_type = 'drupal_module_statistics'): array {
    $query = Database::getConnection()
      ->select('watchdog')
      ->fields('watchdog', ['variables'])
      ->condition('type', $event_type)
      ->execute();
    return $query->fetchAll();
  }

  /**
   * Get database log count for Drupal Module Statistics entry.
   *
   * @param string $event_type
   *   The event type.
   *
   * @return int
   *   The log count.
   */
  public function getLogEntry(string $event_type = 'drupal_module_statistics'): int {
    $data = $this->getLogData($event_type);
    return count($data);
  }

}
