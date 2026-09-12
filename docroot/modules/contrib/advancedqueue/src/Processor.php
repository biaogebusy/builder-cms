<?php

namespace Drupal\advancedqueue;

use Drupal\advancedqueue\Entity\QueueInterface;
use Drupal\advancedqueue\Event\AdvancedQueueEvents;
use Drupal\advancedqueue\Event\JobEvent;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Utility\Error;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides the default queue processor.
 */
class Processor implements ProcessorInterface {

  /**
   * Indicates if the processor should stop.
   *
   * @var bool
   */
  protected $shouldStop = FALSE;

  /**
   * Constructs a new Processor object.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The current time.
   * @param \Drupal\advancedqueue\JobTypeManager $jobTypeManager
   *   The queue job type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   */
  public function __construct(
    protected EventDispatcherInterface $eventDispatcher,
    protected TimeInterface $time,
    protected JobTypeManager $jobTypeManager,
    protected LoggerChannelFactoryInterface $loggerChannelFactory,
  ) {
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
    $stop_when_empty = $queue->getStopWhenEmpty();
    $was_empty = FALSE;

    while (TRUE) {
      if ($this->shouldStop) {
        break;
      }
      $job = $queue->getBackend()->claimJob();
      if (!$job) {
        $was_empty = TRUE;

        if ($stop_when_empty) {
          break;
        }
      }

      if ($job) {
        $this->processJob($job, $queue);
        $num_processed++;
      }

      if ($processing_time && $this->time->getCurrentTime() >= $expected_end) {
        // Time limit reached. Stop here.
        break;
      }

      if ($was_empty) {
        sleep(1);
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
      $this->loggerChannelFactory->get('cron')->error('%type: @message in %function (line %line of %file).', $variables);
    }

    // Update the job with the result.
    $job->setState($result->getState());
    $job->setMessage($result->getMessage());
    $this->eventDispatcher->dispatch(new JobEvent($job), AdvancedQueueEvents::POST_PROCESS);
    // Pass the job back to the backend.
    $queue_backend = $queue->getBackend();
    if ($job->getState() == Job::STATE_SUCCESS) {
      $queue_backend->onSuccess($job);
      $this->eventDispatcher->dispatch(new JobEvent($job), AdvancedQueueEvents::JOB_SUCCESS);
    }
    elseif ($job->getState() == Job::STATE_FAILURE && !$job_type) {
      // The job failed because of an exception, no need to retry.
      $queue_backend->onFailure($job);
      $this->eventDispatcher->dispatch(new JobEvent($job), AdvancedQueueEvents::JOB_FAILURE);
    }
    elseif ($job->getState() == Job::STATE_FAILURE && $job_type) {
      $max_retries = !is_null($result->getMaxRetries()) ? $result->getMaxRetries() : $job_type->getMaxRetries();
      $retry_delay = !is_null($result->getRetryDelay()) ? $result->getRetryDelay() : $job_type->getRetryDelay();
      if ($job->getNumRetries() < $max_retries) {
        $queue_backend->retryJob($job, $retry_delay);
        $this->eventDispatcher->dispatch(new JobEvent($job), AdvancedQueueEvents::JOB_RETRY);
      }
      else {
        $queue_backend->onFailure($job);
        $this->eventDispatcher->dispatch(new JobEvent($job), AdvancedQueueEvents::JOB_FAILURE);
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
