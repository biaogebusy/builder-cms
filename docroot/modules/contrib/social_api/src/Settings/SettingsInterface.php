<?php

namespace Drupal\social_api\Settings;

use Drupal\Core\Config\ImmutableConfig;

/**
 * Defines required methods for a Settings class.
 *
 * @package Drupal\social_api\Settings
 */
interface SettingsInterface {

  /**
   * Gets the configuration object.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The configuration object associated with the settings.
   */
  public function getConfig(): ImmutableConfig;

  /**
   * Factory method to create a new settings object.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The configuration object.
   */
  public static function factory(ImmutableConfig $config): static;

}
