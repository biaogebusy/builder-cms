<?php

declare(strict_types=1);

namespace Drupal\queue_ui\Hook;

use Drupal\Core\State\StateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Class for the queue_ui alter hooks.
 */
final class AlterHooks {

  /**
   * Constructor to inject the state service.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(private StateInterface $state) {}

  /**
   * Hook_queue_info_alter()
   */
  // @phpstan-ignore-next-line
  #[Hook('queue_info_alter')]
  public function queueInfoAlter(&$queues) {// phpcs:ignore Squiz.WhiteSpace.FunctionSpacing.Before
    foreach ($queues as $queueName => $definition) {
      // Check if a time limit override exists for this queue.
      $time_limit = $this->state->get('queue_ui_cron_' . $queueName);
      if ($time_limit === NULL) {
        // Queue UI didn't manage this queue yet.
        continue;
      }
      $time_limit = (string) $time_limit;
      // Check for the value including 0.
      if ($time_limit !== '') {
        // Override the original definition.
        $queues[$queueName]['cron']['time'] = (int) $time_limit;
      }
      else {
        // Disable cron.
        unset($queues[$queueName]['cron']);
      }
    }
  }

}
