<?php

namespace Drupal\acquia_connector;

use Drupal\Core\Config\Config;

/**
 * Acquia Subscription Settings.
 *
 * Single centralized place for accessing and updating Acquia Connector
 * settings. All currently existing configs should be moved here and use Drupal
 * State API instead of Drupal Config.
 *
 * For more info visit https://www.drupal.org/node/2635138.
 */
class Settings {

  /**
   * Acquia Network ID.
   *
   * Eg: ABCD-12345.
   *
   * @var string
   */
  protected $identifier;

  /**
   * The shared secret key.
   *
   * @var string
   */
  protected $secretKey;

  /**
   * The Application UUID.
   *
   * @var string
   */
  protected $applicationUuid;

  /**
   * The endpoint to access subscription data.
   *
   * @var string
   */
  protected $url;

  /**
   * Config object from acquia_connector.settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Readonly status of the Settings object.
   *
   * @var bool
   */
  protected $readonly = TRUE;

  /**
   * Additional Metadata provided by some Settings providers.
   *
   * @var array|mixed
   */
  protected $metadata;

  /**
   * Constructs a Settings object.
   *
   * These settings have a null option to handle initial setup through the
   * ClientFactory. At that point, only config is required.
   *
   * @param \Drupal\Core\Config\Config $config
   *   Static config for Acquia Connector.
   * @param string|null $network_id
   *   Subscription Identifier.
   * @param string|null $secret_key
   *   Secret key.
   * @param string|null $application_uuid
   *   Application UUID.
   * @param array|string $metadata
   *   Settings Metadata.
   */
  public function __construct(Config $config, ?string $network_id = NULL, ?string $secret_key = NULL, ?string $application_uuid = NULL, $metadata = NULL) {
    $this->config = $config;
    $this->identifier = $network_id ?? '';
    $this->secretKey = $secret_key ?? '';
    $this->applicationUuid = $application_uuid ?? '';
    $this->metadata = $metadata ?? [];
  }

  /**
   * Returns Acquia Subscription identifier.
   *
   * @return mixed
   *   Acquia Subscription identifier.
   */
  public function getIdentifier() {
    return $this->identifier ?? NULL;
  }

  /**
   * Returns Acquia Subscription key.
   *
   * @return mixed
   *   Acquia Subscription key.
   */
  public function getSecretKey() {
    return $this->secretKey ?? NULL;
  }

  /**
   * Returns Acquia Subscription Application UUID.
   *
   * @return mixed
   *   Acquia Application UUID identifier.
   */
  public function getApplicationUuid() {
    return $this->applicationUuid ?? NULL;
  }

  /**
   * Returns static connector config settings.
   *
   * @return \Drupal\Core\Config\Config
   *   Acquia Connector Config Object.
   */
  public function getConfig() {
    return $this->config;
  }

  /**
   * Returns the metadata array, or a specific piece of metadata if it exists.
   *
   * @param string|null $key
   *   Metadata key.
   *
   * @return mixed
   *   The Metadata.
   */
  public function getMetadata(?string $key = NULL) {
    if (isset($key) && isset($this->metadata[$key])) {
      return $this->metadata[$key];
    }
    elseif (isset($key)) {
      return [];
    }
    else {
      return $this->metadata;
    }
  }

  /**
   * Deletes all stored data.
   */
  public function deleteAllData() {
    \Drupal::state()->deleteMultiple([
      'acquia_connector.key',
      'acquia_connector.identifier',
      'acquia_connector.application_uuid',
      'spi.site_name',
      'spi.site_machine_name',
      'acquia_subscription_data',
    ]);
  }

  /**
   * Gets readonly status for the settings object.
   *
   * @return bool
   *   Readonly Status.
   */
  public function isReadonly() {
    return $this->readonly;
  }

  /**
   * Sets readonly status for the settings object.
   */
  public function setReadOnly($readonly) {
    $this->readonly = $readonly;
  }

}
