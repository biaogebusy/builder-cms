<?php

namespace Drupal\social_auth_google\Settings;

use Drupal\social_auth\Settings\SettingsInterface;

/**
 * Defines an interface for Social Auth Google settings.
 */
interface GoogleAuthSettingsInterface extends SettingsInterface {

  /**
   * Gets the restricted domain.
   *
   * @return string|null
   *   The restricted domain.
   */
  public function getRestrictedDomain(): ?string;

}
