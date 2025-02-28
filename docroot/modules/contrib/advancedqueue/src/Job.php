<?php

namespace Drupal\advancedqueue;

/**
 * Represents a job.
 */
class Job {

  /**
   * Available states.
   */
  const STATE_QUEUED = 'queued';
  const STATE_PROCESSING = 'processing';
  const STATE_SUCCESS = 'success';
  const STATE_FAILURE = 'failure';

  /**
   * The job ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The queue ID.
   *
   * @var string
   */
  protected $queueId;

  /**
   * The job type.
   *
   * Represents the ID of the plugin called to process the job.
   *
   * @var string
   */
  protected $type;

  /**
   * The job payload.
   *
   * @var array
   */
  protected $payload;

  /**
   * The job state.
   *
   * @var string
   */
  protected $state = self::STATE_QUEUED;

  /**
   * The job message.
   *
   * @var array
   */
  protected $message;

  /**
   * The number of retries.
   *
   * @var int
   */
  protected $numRetries = 0;

  /**
   * The availability timestamp.
   *
   * @var int
   */
  protected $available;

  /**
   * The processing timestamp.
   *
   * @var int
   */
  protected $processed;

  /**
   * The lease expiration timestamp.
   *
   * @var int
   */
  protected $expires;

  /**
   * Constructs a new Job object.
   *
   * @param array $definition
   *   The job definition.
   */
  public function __construct(array $definition) {
    foreach (['type', 'payload', 'state'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new \InvalidArgumentException(sprintf('Missing property "%s"', $required_property));
      }
    }
    $this->assertState($definition['state']);

    $this->id = !empty($definition['id']) ? $definition['id'] : '';
    $this->queueId = !empty($definition['queue_id']) ? $definition['queue_id'] : '';
    $this->type = $definition['type'];
    $this->payload = $definition['payload'];
    $this->state = $definition['state'];
    $this->message = !empty($definition['message']) ? $definition['message'] : NULL;
    $this->numRetries = !empty($definition['num_retries']) ? $definition['num_retries'] : 0;
    $this->available = !empty($definition['available']) ? (int) $definition['available'] : 0;
    $this->processed = !empty($definition['processed']) ? (int) $definition['processed'] : 0;
    $this->expires = !empty($definition['expires']) ? (int) $definition['expires'] : 0;
  }

  /**
   * Creates a new job, ready to be queued.
   *
   * @param string $type
   *   The job type.
   * @param array $payload
   *   The payload.
   *
   * @return static
   */
  public static function create($type, array $payload) {
    return new static([
      'type' => $type,
      'payload' => $payload,
      'state' => self::STATE_QUEUED,
    ]);
  }

  /**
   * Asserts that the given job state is valid.
   *
   * @param string $state
   *   The job state, a Job::STATE_ constant.
   *
   * @throws \InvalidArgumentException
   *   Thrown if the job state is invalid.
   */
  protected function assertState($state) {
    $states = [
      self::STATE_QUEUED,
      self::STATE_PROCESSING,
      self::STATE_SUCCESS,
      self::STATE_FAILURE,
    ];
    if (!in_array($state, $states)) {
      throw new \InvalidArgumentException(sprintf('Invalid state "%s" given.', $state));
    }
  }

  /**
   * Gets the job ID.
   *
   * Assigned to the job during queueing.
   *
   * @return string
   *   The job ID.
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Sets the job ID.
   *
   * @param string $id
   *   The job ID.
   *
   * @return $this
   */
  public function setId($id) {
    $this->id = $id;
    return $this;
  }

  /**
   * Gets the queue ID.
   *
   * Assigned to the job during queueing.
   *
   * @return string
   *   The queue ID.
   */
  public function getQueueId() {
    return $this->queueId;
  }

  /**
   * Sets the queue ID.
   *
   * @param string $queue_id
   *   The queue ID.
   *
   * @return $this
   */
  public function setQueueId($queue_id) {
    $this->queueId = $queue_id;
    return $this;
  }

  /**
   * Gets the job type.
   *
   * @return string
   *   The job type.
   */
  public function getType() {
    return $this->type;
  }

  /**
   * Sets the job type.
   *
   * @param string $type
   *   The job type.
   *
   * @return $this
   */
  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  /**
   * Gets the job payload.
   *
   * @return array
   *   The job payload.
   */
  public function getPayload() {
    return $this->payload;
  }

  /**
   * Sets the job payload.
   *
   * @param array $payload
   *   The job payload.
   *
   * @return $this
   */
  public function setPayload(array $payload) {
    $this->payload = $payload;
    return $this;
  }

  /**
   * Gets the job state.
   *
   * @return string
   *   The job state, a Job::STATE_ constant.
   */
  public function getState() {
    return $this->state;
  }

  /**
   * Sets the job state.
   *
   * @param string $state
   *   The job state, a Job::STATE_ constant.
   *
   * @return $this
   */
  public function setState($state) {
    $this->assertState($state);
    $this->state = $state;
    if ($state != self::STATE_PROCESSING) {
      $this->expires = 0;
    }
    return $this;
  }

  /**
   * Gets the job message.
   *
   * Populated from the JobResult after the job has been processed.
   * Contains the error message if the job failed.
   *
   * @return string
   *   The job message.
   */
  public function getMessage() {
    return $this->message;
  }

  /**
   * Sets the job message.
   *
   * @param string $message
   *   The job message.
   *
   * @return $this
   */
  public function setMessage($message) {
    $this->message = $message;
    return $this;
  }

  /**
   * Gets the number of times this job was retried.
   *
   * @return int
   *   The number of retries.
   */
  public function getNumRetries() {
    return $this->numRetries;
  }

  /**
   * Sets the number of times this job was retried.
   *
   * @param int $num_retries
   *   The number of retries.
   *
   * @return $this
   */
  public function setNumRetries($num_retries) {
    $this->numRetries = $num_retries;
    return $this;
  }

  /**
   * Gets the availability timestamp.
   *
   * Determines when the job should become available to consumers.
   * Allows jobs to be scheduled in the future.
   *
   * @return string
   *   The availability timestamp.
   */
  public function getAvailableTime() {
    return $this->available;
  }

  /**
   * Sets the availability timestamp.
   *
   * @param int $available
   *   The availability timestamp.
   *
   * @return $this
   */
  public function setAvailableTime($available) {
    $this->available = $available;
    return $this;
  }

  /**
   * Gets the processing timestamp.
   *
   * Indicates when the job was last processed.
   *
   * @return int
   *   The processing timestamp.
   */
  public function getProcessedTime() {
    return $this->processed;
  }

  /**
   * Sets the processing timestamp.
   *
   * @param int $processed
   *   The processing timestamp.
   *
   * @return $this
   */
  public function setProcessedTime($processed) {
    $this->processed = $processed;
    return $this;
  }

  /**
   * Gets the lease expiration timestamp.
   *
   * @return int
   *   The lease expiration timestamp.
   */
  public function getExpiresTime() {
    return $this->expires;
  }

  /**
   * Sets the lease expiration timestamp.
   *
   * @param int $expires
   *   The lease expiration timestamp.
   *
   * @return $this
   */
  public function setExpiresTime($expires) {
    $this->expires = $expires;
    return $this;
  }

  /**
   * Returns the job as an array.
   *
   * @return array
   *   The job.
   */
  public function toArray() {
    return [
      'id' => $this->id,
      'queue_id' => $this->queueId,
      'type' => $this->type,
      'payload' => $this->payload,
      'state' => $this->state,
      'message' => $this->message,
      'available' => $this->available,
      'processed' => $this->processed,
      'expires' => $this->expires,
    ];
  }

}
