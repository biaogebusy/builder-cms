<?php

declare(strict_types=1);

namespace Drupal\queue_ui_derivatives_fixtures\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Defines 'channel' queue worker.
 *
 * @QueueWorker(
 *   id = "channel_queue",
 *   title = @Translation("Channels Queue"),
 *   cron = {"time" = 59},
 *   deriver = "\Drupal\queue_ui_derivatives_fixtures\Plugin\Derivative\Channel"
 * )
 */
class ChannelWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {}

}
