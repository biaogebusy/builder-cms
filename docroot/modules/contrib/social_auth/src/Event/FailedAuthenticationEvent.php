<?php

namespace Drupal\social_auth\Event;

use Drupal\social_auth\SocialAuthDataHandler;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Dispatched when user authentication fails in provider.
 *
 * @see \Drupal\social_auth\Event\SocialAuthEvents
 */
class FailedAuthenticationEvent extends SocialAuthEventBase {

  /**
   * The Social Auth data handler.
   *
   * @var \Drupal\social_auth\SocialAuthDataHandler
   */
  protected SocialAuthDataHandler $dataHandler;

  /**
   * The error string.
   *
   * @var string
   */
  protected string $error;

  /**
   * RedirectResponse object.
   *
   * @var \Symfony\Component\HttpFoundation\RedirectResponse|null
   */
  protected ?RedirectResponse $response;

  /**
   * FailedAuthenticationEvent constructor.
   *
   * @param \Drupal\social_auth\SocialAuthDataHandler $data_handler
   *   The Social Auth data handler.
   * @param string $plugin_id
   *   The plugin ID dispatching this event.
   * @param string|null $error
   *   The error string.
   */
  public function __construct(SocialAuthDataHandler $data_handler, string $plugin_id, ?string $error = NULL) {
    $this->dataHandler = $data_handler;
    $this->pluginId = $plugin_id;
    $this->error = $error;
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
   * Gets the error string from provider.
   *
   * @return string
   *   The error string.
   */
  public function getError(): string {
    return $this->error;
  }

  /**
   * Returns the current redirect response object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The response from the provider.
   */
  public function getResponse(): RedirectResponse {
    return $this->response;
  }

  /**
   * Sets a new redirect response object.
   *
   * @param \Symfony\Component\HttpFoundation\RedirectResponse $response
   *   The response from the provider.
   */
  public function setResponse(RedirectResponse $response): void {
    $this->response = $response;
  }

  /**
   * Returns whether a redirect response was set.
   *
   * @return bool
   *   Whether a response was set.
   */
  public function hasResponse(): bool {
    return $this->response !== NULL;
  }

}
