<?php

declare(strict_types=1);

namespace Drupal\private_message\Traits;

use Drupal\Core\Config\ImmutableConfig;

/**
 * Private Message module settings.
 */
trait PrivateMessageSettingsTrait {

  /**
   * Static cache of Private Message module settings.
   */
  protected ImmutableConfig $privateMessageSettings;

  /**
   * Returns the private message module settings.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The private message module settings.
   */
  protected function getPrivateMessageSettings(): ImmutableConfig {
    assert(property_exists($this, 'configFactory'));
    if (!isset($this->privateMessageSettings)) {
      $this->privateMessageSettings = $this->configFactory->get('private_message.settings');
    }
    return $this->privateMessageSettings;
  }

}
