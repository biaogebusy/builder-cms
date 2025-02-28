<?php

namespace Drupal\queue_ui;

/**
 * An interface for batch controller to process a queue.
 *
 * @package Drupal\queue_ui
 */
interface QueueUIBatchInterface {

  /**
   * Prepares and executes batches.
   *
   * @param string[] $queues
   *   Queues names to process.
   */
  public function batch(array $queues);

  /**
   * Batch step definition to process a queue.
   *
   * Each time the step is executed an item on the queue will be processed.
   * The batch job will be marked as finished when the queue is empty.
   *
   * Based on \Drupal\Core\Cron::processQueues().
   *
   * @param string $queue_name
   *   The name of the queue being inspected.
   * @param array|\DrushBatchContext $context
   *   An associative array or DrushBatchContext.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function step(string $queue_name, &$context);

  /**
   * Callback when finishing a batch job.
   *
   * @param bool $success
   *   Indicate that the batch API tasks were all completed successfully.
   * @param array $results
   *   The value set in $context['results'] by callback_batch_operation().
   * @param array $operations
   *   If $success is FALSE, contains the operations that remained unprocessed.
   */
  public function finish(bool $success, array $results, array $operations);

}
