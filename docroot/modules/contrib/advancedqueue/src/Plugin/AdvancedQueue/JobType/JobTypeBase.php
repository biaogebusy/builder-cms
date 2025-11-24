<?php

namespace Drupal\advancedqueue\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Exception\DuplicateJobException;
use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\Plugin\AdvancedQueue\Backend\BackendInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Provides the base class for job types.
 */
abstract class JobTypeBase extends PluginBase implements JobTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxRetries() {
    return $this->pluginDefinition['max_retries'];
  }

  /**
   * {@inheritdoc}
   */
  public function getRetryDelay() {
    return $this->pluginDefinition['retry_delay'];
  }

  /**
   * {@inheritdoc}
   */
  public function createJobFingerprint(Job $job): string {
    // Use the queue name, the job type, and the payload to create a hash.
    $data = $job->getQueueId() . $job->getType() . serialize($job->getPayload());

    // crc32b is the fastest but has collisions due to its short length.
    // sha1 and md5 are forbidden by many projects and organizations.
    // This is the next fastest option.
    $hash = hash('tiger128,3', $data);

    return $hash;
  }

  /**
   * {@inheritdoc}
   */
  public function handleDuplicateJobs(Job $job, array $duplicates, BackendInterface $backend): ?Job {
    throw new DuplicateJobException("Job rejected because it duplicates existing jobs.", $job, $duplicates);
  }

}
