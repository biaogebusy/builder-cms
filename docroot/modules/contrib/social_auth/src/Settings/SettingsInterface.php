<?php

namespace Drupal\social_auth\Settings;

/**
 * Defines an interface for Social Auth provider settings.
 *
 * @package Drupal\social_auth\Settings
 */
interface SettingsInterface {

  /**
   * Gets the client ID.
   *
   * @return string|null
   *   The client ID.
   */
  public function getClientId(): ?string;

  /**
   * Gets the client secret.
   *
   * @return string|null
   *   The client secret.
   */
  public function getClientSecret(): ?string;

}
