<?php

namespace Drupal\social_auth\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Base class for the Social Auth events.
 *
 * @see \Drupal\social_auth\Event\SocialAuthEvents
 */
abstract class SocialAuthEventBase extends Event {
  /**
   * The plugin id dispatching this event.
   *
   * @var string
   */
  protected string $pluginId;

  /**
   * Gets the plugin id dispatching this event.
   *
   * @return string
   *   The plugin id.
   */
  public function getPluginId(): string {
    return $this->pluginId;
  }

}
