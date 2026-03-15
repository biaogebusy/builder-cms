<?php

namespace Drupal\social_api;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Variables are written to and read from session via this class.
 */
abstract class SocialApiDataHandler {

  /**
   * The session service.
   */
  protected SessionInterface $session;

  /**
   * The prefix each session variable will have.
   */
  protected string $sessionPrefix;

  /**
   * Constructor.
   */
  public function __construct(SessionInterface $session) {
    $this->session = $session;
  }

  /**
   * Gets a session variable by key.
   */
  public function get(string $key): mixed {
    return $this->session->get($this->getSessionPrefix() . $key);
  }

  /**
   * Sets a new session variable.
   */
  public function set(string $key, mixed $value): void {
    $this->session->set($this->getSessionPrefix() . $key, $value);
  }

  /**
   * Gets the session prefix for the data handler.
   */
  public function getSessionPrefix(): string {
    return $this->sessionPrefix;
  }

  /**
   * Sets the session prefix for the data handler.
   */
  public function setSessionPrefix(string $prefix): void {
    $this->sessionPrefix = "{$prefix}_";
  }

  /**
   * Gets the session being used by the data handler.
   */
  public function getSession(): SessionInterface {
    return $this->session;
  }

}
