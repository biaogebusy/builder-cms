<?php

namespace Drupal\social_auth\Settings;

use Drupal\social_api\Settings\SettingsBase as SocialApiSettingsBase;

/**
 * Defines default settings for Social Auth providers.
 *
 * @package Drupal\social_auth\Settings
 */
class SettingsBase extends SocialApiSettingsBase implements SettingsInterface {

  /**
   * Client ID.
   *
   * @var string|null
   */
  protected ?string $clientId = NULL;

  /**
   * Client secret.
   *
   * @var string|null
   */
  protected ?string $clientSecret = NULL;

  /**
   * {@inheritdoc}
   */
  public function getClientId(): ?string {
    if (!$this->clientId) {
      $this->clientId = $this->config->get('client_id');
    }
    return $this->clientId;
  }

  /**
   * {@inheritdoc}
   */
  public function getClientSecret(): ?string {
    if (!$this->clientSecret) {
      $this->clientSecret = $this->config->get('client_secret');
    }
    return $this->clientSecret;
  }

}
