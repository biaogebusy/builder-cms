<?php

namespace Drupal\social_auth\Event;

use Drupal\social_auth\SocialAuthDataHandler;

/**
 * Dispatched before user is redirected to provider for authentication.
 *
 * @see \Drupal\social_auth\Event\SocialAuthEvents
 */
class BeforeRedirectEvent extends SocialAuthEventBase {

  /**
   * The Social Auth data handler.
   *
   * @var \Drupal\social_auth\SocialAuthDataHandler
   */
  protected SocialAuthDataHandler $dataHandler;

  /**
   * The destination where use will redirect after successful authentication.
   *
   * @var string|null
   */
  protected ?string $destination = NULL;

  /**
   * BeforeRedirectEvent constructor.
   *
   * @param \Drupal\social_auth\SocialAuthDataHandler $data_handler
   *   The Social Auth data handler.
   * @param string $plugin_id
   *   The plugin ID dispatching this event.
   * @param string|null $destination
   *   The destination where user will redirect after successful authentication.
   */
  public function __construct(SocialAuthDataHandler $data_handler, string $plugin_id, ?string $destination = NULL) {
    $this->dataHandler = $data_handler;
    $this->pluginId = $plugin_id;
    $this->destination = $destination;
  }

  /**
   * Gets the Social Auth data handler object.
   *
   * @return \Drupal\social_auth\SocialAuthDataHandler
   *   The Social Auth data handler.
   */
  public function getDataHandler(): SocialAuthDataHandler {
    return $this->dataHandler;
  }

  /**
   * Gets the destination.
   *
   * @return string
   *   The destination path.
   */
  public function getDestination(): string {
    return $this->destination;
  }

}
