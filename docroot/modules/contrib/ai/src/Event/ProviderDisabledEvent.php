<?php

namespace Drupal\ai\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event that is fired when an AI provider is disabled.
 *
 * This event allows other 3rd party modules to react to an AI provider being
 * disabled, that might have been providing functionality to that module.
 */
class ProviderDisabledEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai.provider_disabled';

  /**
   * The provider that was disabled.
   *
   * @var string
   */
  protected $providerId;

  /**
   * Constructor.
   *
   * @param string $provider_id
   *   The provider that was disabled.
   */
  public function __construct($provider_id) {
    $this->providerId = $provider_id;
  }

  /**
   * Get the provider that was disabled.
   *
   * @return string
   *   The provider that was disabled.
   */
  public function getProviderId() {
    return $this->providerId;
  }

}
