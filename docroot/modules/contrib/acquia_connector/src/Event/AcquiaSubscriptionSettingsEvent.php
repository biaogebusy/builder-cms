<?php

namespace Drupal\acquia_connector\Event;

use Drupal\acquia_connector\Settings;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * The event to find settings for Acquia Connector.
 */
class AcquiaSubscriptionSettingsEvent extends EventBase {

  /**
   * The Acquia Connector settings object.
   *
   * @var \Drupal\acquia_connector\Settings
   */
  protected $settings;

  /**
   * The provider of the settings configuration.
   *
   * @var string
   */
  protected $provider;

  /**
   * Acquia Connector static settings config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Pass in connector config by default to all events.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Acquia Connector settings.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->getEditable('acquia_connector.settings');
  }

  /**
   * Gets the Acquia Connector settings object.
   *
   * @return \Drupal\acquia_connector\Settings
   *   The Acquia settings.
   */
  public function getSettings() {
    return $this->settings;
  }

  /**
   * Return the static Acquia Settings config array.
   *
   * @return \Drupal\Core\Config\Config
   *   The Config Object.
   */
  public function getConfig() {
    return $this->config;
  }

  /**
   * Set the Acquia settings object.
   *
   * @param \Drupal\acquia_connector\Settings $settings
   *   The client settings.
   */
  public function setSettings(Settings $settings) {
    $this->settings = $settings;
  }

  /**
   * Gets the providers of the settings object.
   *
   * @return string
   *   The Provider.
   */
  public function getProvider() {
    return $this->provider;
  }

  /**
   * Sets the provider of the settings object.
   *
   * @param string $provider
   *   The Provider.
   */
  public function setProvider($provider) {
    $this->provider = $provider;
  }

}
