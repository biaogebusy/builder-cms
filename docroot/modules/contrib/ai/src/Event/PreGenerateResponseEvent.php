<?php

namespace Drupal\ai\Event;

use Drupal\ai\OperationType\OutputInterface;

/**
 * Changes or Exceptions before a AI request is triggered can be done here.
 */
class PreGenerateResponseEvent extends AiProviderRequestBaseEvent {

  // The event name.
  const EVENT_NAME = 'ai.pre_generate_response';

  /**
   * The authentication.
   *
   * @var mixed
   */
  protected $authentication;

  /**
   * The output to force return.
   *
   * This is used if a third party wants to stop a request from being sent,
   * gracefully with an expected response, instead of throwing an exception.
   * Examples could be that you want to return a cached response, or a default
   * response that the user does not have access to use AI.
   *
   * @var \Drupal\ai\OperationType\OutputInterface|null
   *   The output.
   */
  protected ?OutputInterface $forcedOutputObject = NULL;

  /**
   * Sets a new authentication layer.
   *
   * @param mixed $authentication
   *   The authentication.
   */
  public function setAuthentication(mixed $authentication): void {
    $this->authentication = $authentication;
  }

  /**
   * Gets the authentication.
   *
   * Note: This only gets a new authentication layer if set. It does not return
   * the default authentication.
   *
   * @return mixed
   *   The authentication.
   */
  public function getAuthentication(): mixed {
    return $this->authentication;
  }

  /**
   * Gets the forced output object.
   *
   * @return \Drupal\ai\OperationType\OutputInterface|null
   *   The output.
   */
  public function getForcedOutputObject(): ?OutputInterface {
    return $this->forcedOutputObject;
  }

  /**
   * Sets the forced output object.
   *
   * @param \Drupal\ai\OperationType\OutputInterface $forced_output_object
   *   The output.
   */
  public function setForcedOutputObject(OutputInterface $forced_output_object): void {
    $this->forcedOutputObject = $forced_output_object;
  }

}
