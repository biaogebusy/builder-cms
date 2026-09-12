<?php

namespace Drupal\advancedqueue\Event;

/**
 * Defines events for Advanced queue.
 */
final class AdvancedQueueEvents {

  /**
   * Name of the event fired before processing a job.
   *
   * @Event
   *
   * @see \Drupal\advancedqueue\Event\JobEvent
   */
  const PRE_PROCESS = 'advancedqueue.pre_process';

  /**
   * Name of the event fired after processing a job.
   *
   * Fired before the job is passed back to the backend, allowing event
   * subscribers to modify it when needed.
   *
   * @Event
   *
   * @see \Drupal\advancedqueue\Event\JobEvent
   */
  const POST_PROCESS = 'advancedqueue.post_process';

  /**
   * Name of the event fired when a job was processed successfully.
   *
   * @Event
   *
   * @see \Drupal\advancedqueue\Event\JobEvent
   */
  const JOB_SUCCESS = 'advancedqueue.job.success';

  /**
   * Name of the event fired when a job failed and was marked for a retry.
   *
   * @Event
   *
   * @see \Drupal\advancedqueue\Event\JobEvent
   */
  const JOB_RETRY = 'advancedqueue.job.retry';

  /**
   * Name of the event fired when a job failed and reached max retries.
   *
   * @Event
   *
   * @see \Drupal\advancedqueue\Event\JobEvent
   */
  const JOB_FAILURE = 'advancedqueue.job.failure';

}
