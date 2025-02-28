<?php

namespace Drupal\advancedqueue;

use Drupal\advancedqueue\Entity\QueueInterface;
use Drupal\advancedqueue\Event\AdvancedQueueEvents;
use Drupal\advancedqueue\Event\JobEvent;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Utility\Error;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides the default queue processor.
 *
 * @todo Throw events.
 */
class Processor implements ProcessorInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The current time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The job type manager.
   *
   * @var \Drupal\advancedqueue\JobTypeManager
   */
  protected $jobTypeManager;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Indicates if the processor should stop.
   *
   * @var bool
   */
  protected $shouldStop = FALSE;

  /**
   * Constructs a new Processor object.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The current time.
   * @param \Drupal\advancedqueue\JobTypeManager $job_type_manager
   *   The queue job type manager.
   * @param \Psr\Log\LoggerInterface|null $logger
   *   The logger.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher, TimeInterface $time, JobTypeManager $job_type_manager, LoggerInterface $logger = NULL) {
    if ($logger === NULL) {
      @trigger_error('Calling Processor::__construct() without the $logger argument is deprecated in advancedqueue:8.x-1.0 and will be required before advancedqueue:8.x-2.0. See https://www.drupal.org/project/advancedqueue/issues/3192113', E_USER_DEPRECATED);
      $logger = \Drupal::service('logger.channel.cron');
    }
    $this->eventDispatcher = $event_dispatcher;
    $this->time = $time;
    $this->jobTypeManager = $job_type_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function processQueue(QueueInterface $queue) {
    // Start from a clean slate.
    $queue->getBackend()->cleanupQueue();

    $this->shouldStop = FALSE;

    // Allow unlimited processing time only on the CLI.
    $processing_time = $queue->getProcessingTime();
    if ($processing_time == 0 && PHP_SAPI != 'cli') {
      $processing_time = 90;
    }
    $expected_end = $this->time->getCurrentTime() + $processing_time;
    $num_processed = 0;

    while (TRUE) {
      if ($this->shouldStop) {
        break;
      }
      $job = $queue->getBackend()->claimJob();
      if (!$job) {
        // The queue is empty. Stop here.
        break;
      }
      $this->processJob($job, $queue);
      $num_processed++;

      if ($processing_time && $this->time->getCurrentTime() >= $expected_end) {
        // Time limit reached. Stop here.
        break;
      }
    }

    return $num_processed;
  }

  /**
   * {@inheritdoc}
   */
  public function processJob(Job $job, QueueInterface $queue) {
    $this->eventDispatcher->dispatch(new JobEvent($job), AdvancedQueueEvents::PRE_PROCESS);

    try {
      $job_type = $this->jobTypeManager->createInstance($job->getType());
      $result = $job_type->process($job);
    }
    catch (\Throwable $e) {
      $job_type = NULL;
      $result = JobResult::failure($e->getMessage());

      $variables = Error::decodeException($e);
      $this->logger->error('%type: @message in %function (line %line of %file).', $variables);
    }

    // Update the job with the result.
    $job->setState($result->getState());
    $job->setMessage($result->getMessage());
    $this->eventDispatcher->dispatch(new JobEvent($job), AdvancedQueueEvents::POST_PROCESS);
    // Pass the job back to the backend.
    $queue_backend = $queue->getBackend();
    if ($job->getState() == Job::STATE_SUCCESS) {
      $queue_backend->onSuccess($job);
    }
    elseif ($job->getState() == Job::STATE_FAILURE && !$job_type) {
      // The job failed because of an exception, no need to retry.
      $queue_backend->onFailure($job);
    }
    elseif ($job->getState() == Job::STATE_FAILURE && $job_type) {
      $max_retries = !is_null($result->getMaxRetries()) ? $result->getMaxRetries() : $job_type->getMaxRetries();
      $retry_delay = !is_null($result->getRetryDelay()) ? $result->getRetryDelay() : $job_type->getRetryDelay();
      if ($job->getNumRetries() < $max_retries) {
        $queue_backend->retryJob($job, $retry_delay);
      }
      else {
        $queue_backend->onFailure($job);
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function stop() {
    $this->shouldStop = TRUE;
  }

}
