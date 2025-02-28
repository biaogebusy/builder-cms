<?php

namespace Drupal\advancedqueue;

/**
 * Represents a job result.
 */
class JobResult {

  /**
   * The state.
   *
   * Job::STATE_SUCCESS or JOB::STATE_FAILURE.
   *
   * @var string
   */
  protected $state;

  /**
   * The message.
   *
   * @var string
   */
  protected $message;

  /**
   * The maximum number of retries.
   *
   * @var int
   */
  protected $maxRetries;

  /**
   * The retry delay.
   *
   * @var int
   */
  protected $retryDelay;

  /**
   * Constructs a new JobResult object.
   *
   * @param string $state
   *   The state. Job::STATE_SUCCESS or JOB::STATE_FAILURE.
   * @param string $message
   *   The message. Optional.
   * @param int $max_retries
   *   The maximum number of retries.
   * @param int $retry_delay
   *   The retry delay, in seconds.
   */
  public function __construct($state, $message = '', $max_retries = NULL, $retry_delay = NULL) {
    $this->state = $state;
    $this->message = $message;
    $this->maxRetries = $max_retries;
    $this->retryDelay = $retry_delay;
  }

  /**
   * Constructs a success result.
   *
   * @param string $message
   *   The message. Optional.
   *
   * @return static
   */
  public static function success($message = '') {
    return new static(Job::STATE_SUCCESS, $message);
  }

  /**
   * Constructs a failure result.
   *
   * The job type's default retry behavior can be overridden by passing
   * custom $max_retries and $retry_delay values to this method.
   *
   * @param string $message
   *   The message. Optional.
   * @param int $max_retries
   *   The maximum number of times. Optional.
   * @param int $retry_delay
   *   The retry delay, in seconds. Optional.
   *
   * @return static
   */
  public static function failure($message = '', $max_retries = NULL, $retry_delay = NULL) {
    return new static(Job::STATE_FAILURE, $message, $max_retries, $retry_delay);
  }

  /**
   * Gets the state.
   *
   * @return string
   *   The state. Job::STATE_SUCCESS or JOB::STATE_FAILURE.
   */
  public function getState() {
    return $this->state;
  }

  /**
   * Gets the message.
   *
   * @return string
   *   The message.
   */
  public function getMessage() {
    return $this->message;
  }

  /**
   * Gets the maximum number of retries.
   *
   * @return int|null
   *   The maximum number of retries, or NULL if the job type default
   *   should be used.
   */
  public function getMaxRetries() {
    return $this->maxRetries;
  }

  /**
   * Gets the retry delay.
   *
   * @return int|null
   *   The retry delay, or NULL if the job type default should be used.
   */
  public function getRetryDelay() {
    return $this->retryDelay;
  }

}
