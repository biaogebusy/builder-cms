<?php

namespace Drupal\acquia_connector\Services;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;

/**
 * Acquia Telemetry Service.
 *
 * This event logs anonymized data on Acquia to help track modules and versions
 * Acquia sites use to ensure module updates don't break customer sites.
 *
 * @package Drupal\acquia_connector
 */
final class AcquiaTelemetryService {

  /**
   * Acquia Telemetry Data.
   *
   * @var array
   */
  protected $telemetryData;

  /**
   * The extension.list.module service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleList;

  /**
   * The config.factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Constructs a telemetry object.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The extension.list.module service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config.factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger factory.
   */
  public function __construct(ModuleExtensionList $module_list, ConfigFactoryInterface $config_factory, StateInterface $state, LoggerChannelFactoryInterface $logger) {
    $this->moduleList = $module_list;
    $this->configFactory = $config_factory;
    $this->state = $state;
    $this->logger = $logger;
  }

  /**
   * Creates and logs event to dblog/syslog.
   *
   * @param string $event_type
   *   The event type.
   * @param array $event_properties
   *   (optional) Event properties.
   *
   * @throws \Exception
   *   Thrown if state key acquia_telemetry.loud is TRUE and request fails.
   *
   * @see https://help.sumologic.com/docs/search/lookup-tables/create-lookup-table/#reserved-keywords
   */
  public function sendTelemetry(string $event_type, array $event_properties = []): void {
    $telemetry_data = $this->getTelemetryData($event_type, $event_properties);
    // Convert the pretty name for events to machine name.
    $event_type_machine = preg_replace('@[^a-z0-9-]+@', '_', strtolower($event_type));
    if ($this->shouldSendTelemetryData($event_type_machine, $telemetry_data)) {
      // Failure to send Telemetry should never cause a user facing error or
      // interrupt a process. Telemetry failure should be graceful and quiet.
      try {
        $this->logger->get($event_type_machine)->info('@message', [
          '@message' => json_encode($telemetry_data, JSON_UNESCAPED_SLASHES),
        ]);

        $this->state->set("acquia_connector.telemetry.$event_type_machine.hash", $this->getHash($telemetry_data));
      }
      catch (\Exception $e) {
        if ($this->state->get('acquia_connector.telemetry.loud')) {
          throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }
      }
      // Cache the telemetry data for the rest of the request.
      $this->telemetryData[$event_type_machine] = $telemetry_data;
    }
  }

  /**
   * Gets an array of all Acquia Drupal extensions.
   *
   * @return array
   *   A flat array of all Acquia Drupal extensions.
   */
  public function getAcquiaExtensionNames(): array {
    $module_names = array_keys($this->moduleList->getAllAvailableInfo());

    return array_values(array_filter($module_names, function ($name) {
      return $name === 'cohesion' || strpos($name, 'acquia') !== FALSE ||
        strpos($name, 'lightning_') !== FALSE ||
        strpos($name, 'sitestudio') !== FALSE;
    }));
  }

  /**
   * Get an array of information about Lightning extensions.
   *
   * @return array
   *   An array of extension info keyed by the extensions machine name. E.g.,
   *   ['lightning_layout' => ['version' => '8.2.0', 'status' => 'enabled']].
   */
  private function getExtensionInfo(): array {
    $all_modules = $this->moduleList->getAllAvailableInfo();
    $installed_modules = $this->moduleList->getAllInstalledInfo();
    $all_paths = $this->moduleList->getPathNames();

    // Set default values for extension info to avoid type errors.
    $extension_info = [
      'core' => [],
      'contrib' => [],
      'profile' => [],
    ];

    foreach ($all_modules as $name => $extension) {
      // Extensions without an associated path should be removed from reporting.
      if (!isset($all_paths[$name])) {
        continue;
      }

      // Remove all custom modules from reporting.
      if (strpos($all_paths[$name], '/custom/') !== FALSE) {
        continue;
      }
      // Track profiles located in core or contrib folders.
      if (strpos($all_paths[$name], 'core/profiles/') !== FALSE || (strpos($all_paths[$name], 'profiles/contrib/') !== FALSE)) {
        $extension_info['profile'][$name]['version'] = $extension['version'] ?? 'dev';
        $extension_info['profile'][$name]['status'] = array_key_exists($name, $installed_modules) ? 'enabled' : 'disabled';
        continue;
      }

      // This should be rewritten so core and contrib report in the same format.
      if (strpos($all_paths[$name], 'core/modules/') !== FALSE) {
        if (array_key_exists($name, $installed_modules)) {
          $extension_info['core'][$name] = 'enabled';
        }
        continue;
      }

      // Version is unset for dev versions. In order to generate reports, we
      // need some value for version, even if it is just the major version.
      $extension_info['contrib'][$name]['version'] = $extension['version'] ?? 'dev';

      // Check if module is installed.
      $extension_info['contrib'][$name]['status'] = array_key_exists($name, $installed_modules) ? 'enabled' : 'disabled';
    }

    return $extension_info;
  }

  /**
   * Creates a telemetry event.
   *
   * @param string $type
   *   The event type.
   * @param array $properties
   *   The event properties.
   *
   * @return array
   *   A telemetry event with basic info already populated.
   */
  private function getTelemetryData(string $type, array $properties): array {
    $modules = $this->getExtensionInfo();
    $default_properties = [
      'extensions' => $modules['contrib'],
      'profiles' => $modules['profile'],
      'php' => [
        'version' => phpversion(),
      ],
      'drupal' => [
        'version' => \Drupal::VERSION,
        'core_enabled' => $modules['core'],
      ],
    ];

    return [
      'event_type' => $type,
      'user_id' => $this->getUserId(),
      'event_properties' => NestedArray::mergeDeep($default_properties, $properties),
    ];
  }

  /**
   * Check current environment.
   *
   * @return bool
   *   TRUE if Acquia production environment, otherwise FALSE.
   */
  private function isAcquiaProdEnv(): bool {

    $ahEnv = getenv('AH_SITE_ENVIRONMENT');
    $ahEnv = preg_replace('/[^a-zA-Z0-9]+/', '', $ahEnv);

    // phpcs:disable
    // ACSF Sites should use the pre-configured env and db roles instead.
    if (isset($GLOBALS['gardens_site_settings'])) {
      $ahEnv = $GLOBALS['gardens_site_settings']['env'];
    }
    // phpcs:enable

    return ($ahEnv === 'prod' || preg_match('/^\d*live$/', $ahEnv));
  }

  /**
   * Decides if telemetry data should send or not.
   *
   * @param array $event_type
   *   The Event type name.
   * @param array $telemetry_data
   *   The array of telemetry data.
   *
   * @return bool
   *   TRUE if condition allow to send data, otherwise FALSE.
   */
  private function shouldSendTelemetryData(string $event_type, array $telemetry_data): bool {

    // Do not send telemetry data if we've already sent it in this request.
    if (isset($this->telemetryData[$event_type])) {
      return FALSE;
    }

    // Only send telemetry data if we're in a production environment.
    if (!$this->isAcquiaProdEnv()) {
      return FALSE;
    }

    // Send telemetry data if there is change in current data to send
    // and previous sent telemetry data.
    if ($this->state->get("acquia_connector.telemetry.$event_type.hash") == $this->getHash($telemetry_data)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Gets a unique ID for this application. "User ID" to group all request.
   *
   * @return string
   *   Returns a hashed site uuid.
   */
  private function getUserId(): string {
    // In some cases, site uuid isn't available, return anonymous user id.
    $site_uuid = $this->configFactory->get('system.site')->get('uuid') ?? 'UNKNOWN';
    return Crypt::hashBase64($site_uuid);
  }

  /**
   * Gets a unique hash for telemetry data.
   *
   * @param array $telemetry_data
   *   The array of telemetry data.
   *
   * @return string
   *   Returns a hash of telemetry data.
   */
  private function getHash($telemetry_data): string {
    return Crypt::hashBase64(serialize($telemetry_data));
  }

}
