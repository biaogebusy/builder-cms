<?php

declare(strict_types = 1);

namespace Drupal\sms\Provider;

/**
 * Interface for SMS Queue Processor.
 */
interface SmsQueueProcessorInterface {

  /**
   * Check for messages not in the Drupal queue and add them.
   *
   * @todo rename?
   */
  public function processUnqueued();

  /**
   * Delete messages which have been processed and are expired.
   */
  public function garbageCollection();

}
