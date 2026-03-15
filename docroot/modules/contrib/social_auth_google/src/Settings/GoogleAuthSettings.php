<?php

namespace Drupal\social_auth_google\Settings;

use Drupal\social_auth\Settings\SettingsBase;

/**
 * Defines methods to get Social Auth Google settings.
 */
class GoogleAuthSettings extends SettingsBase implements GoogleAuthSettingsInterface {

  /**
   * Restricted domain.
   *
   * @var string|null
   */
  protected ?string $restrictedDomain = NULL;

  /**
   * {@inheritdoc}
   */
  public function getRestrictedDomain(): ?string {
    if (!$this->restrictedDomain) {
      $this->restrictedDomain = $this->config->get('restricted_domain');
    }
    return $this->restrictedDomain;
  }

}
