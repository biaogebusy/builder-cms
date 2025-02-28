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

}
