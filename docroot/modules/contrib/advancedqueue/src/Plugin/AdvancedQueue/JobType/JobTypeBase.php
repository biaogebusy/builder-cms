<?php

namespace Drupal\advancedqueue\Plugin\AdvancedQueue\JobType;

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

}
