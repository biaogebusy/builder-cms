<?php

namespace Drupal\queue_ui\Commands;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\queue_ui\QueueUIBatchInterface;
use Drupal\queue_ui\QueueUIManager;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * Provide Drush command for queue_ui module.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class QueueUiCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * Queue worker manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueWorkerManager;

  /**
   * Queue UI manager.
   *
   * @var \Drupal\queue_ui\QueueUIManager
   */
  protected $queueUiManager;

  /**
   * Queue UI Batch service.
   *
   * @var \Drupal\queue_ui\QueueUIBatchInterface
   */
  protected $queueUiBatch;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_worker_manager
   *   Queue worker manager.
   * @param \Drupal\queue_ui\QueueUIManager $queue_ui_manager
   *   Queue UI manager.
   * @param \Drupal\queue_ui\QueueUIBatchInterface $queue_ui_batch
   *   Queue UI Batch service.
   */
  public function __construct(QueueWorkerManagerInterface $queue_worker_manager, QueueUIManager $queue_ui_manager, QueueUIBatchInterface $queue_ui_batch) {
    parent::__construct();
    $this->queueWorkerManager = $queue_worker_manager;
    $this->queueUiManager = $queue_ui_manager;
    $this->queueUiBatch = $queue_ui_batch;
  }

  /**
   * Get queue UI manager.
   *
   * @return \Drupal\queue_ui\QueueUIManager
   *   Queue UI manager.
   */
  public function queueUiManager(): QueueUIManager {
    return $this->queueUiManager;
  }

  /**
   * Get queue worker manager.
   *
   * @return \Drupal\Core\Queue\QueueWorkerManagerInterface
   *   Queue worker manager.
   */
  protected function queueWorkerManager(): QueueWorkerManagerInterface {
    return $this->queueWorkerManager;
  }

  /**
   * Process queue.
   *
   * @param string|null $queueName
   *   The name of the queue being inspected.
   *
   * @command queue:process
   * @aliases qp,queue-process
   */
  public function process(string $queueName = NULL) {
    // Require the choice name to be filled.
    if ($queueName = $this->queueChoice($queueName)) {
      // Add operations and start to batch process.
      $this->startBatch(
        (new BatchBuilder())
          ->addOperation([$this->queueUiBatch, 'step'], [$queueName])
      );
    }
  }

  /**
   * Process all queues.
   *
   * @command queue:process-all
   * @aliases qpa,queue-process-all
   */
  public function processAll() {
    $batch = new BatchBuilder();

    $queues = $this->queueWorkerManager()->getDefinitions();
    // Add operations for each queue.
    foreach ($queues as $queueName => $queue_definition) {
      $batch->addOperation([$this->queueUiBatch, 'step'], [$queueName]);
    }

    // Start batch process.
    $this->startBatch($batch);
  }

  /**
   * Remove lease from queue.
   *
   * @param string|null $queueName
   *   The name of the queue being inspected.
   *
   * @command queue:release
   * @aliases qr,queue-release
   *
   * @throws \Drush\Exceptions\UserAbortException
   */
  public function release(string $queueName = NULL): void {
    // Require the choice name to be filled.
    if ($queueName = $this->queueChoice($queueName)) {
      $this->releaseQueue($queueName);
    }
  }

  /**
   * Remove lease from all queue.
   *
   * @command queue:release-all
   * @aliases qra,queue-release-all
   */
  public function releaseAll() {
    $queues = $this->queueWorkerManager()->getDefinitions();
    // Release each queue.
    foreach ($queues as $queueName => $queue_definition) {
      $this->releaseQueue($queueName);
    }
  }

  /**
   * Remove leases from all items in a queue.
   *
   * @param string $queueName
   *   The name of the queue being inspected.
   */
  protected function releaseQueue(string $queueName): void {
    /** @var \Drupal\queue_ui\QueueUIInterface $queue_ui */
    $queue_ui = $this->queueUiManager()
      ->fromQueueName($queueName);

    // Remove leases.
    $num_updated = $queue_ui->releaseItems($queueName);

    $this->logger()->info($this->t('@count lease reset in queue @name', [
      '@count' => $num_updated,
      '@name' => $queueName,
    ]));
  }

  /**
   * Give the user a choice prompt.
   *
   * @param string|null $queueName
   *   The name of the queue being inspected.
   *
   * @return string
   *   The queue name.
   *
   * @throws \Drush\Exceptions\UserAbortException
   */
  protected function queueChoice(string $queueName = NULL): string {
    // Queue name is not provided.
    if (empty($queueName)) {
      // Get all defined queue names.
      $defined_queues = $this->queueWorkerManager()->getDefinitions();

      $queueNames = [];
      foreach ($defined_queues as $queue) {
        // Render queue title.
        $queueNames[$queue['id']] = $queue['title']->render();
      }

      // Show a list of all defined queues.
      $queueName = $this->io()
        ->choice($this->t('Which queue do you want to process?'), $queueNames);
    }

    return $queueName;
  }

  /**
   * Helper function to start a batch process.
   *
   * @param \Drupal\Core\Batch\BatchBuilder $batch_definition
   *   The batch that needs to be processed.
   */
  protected function startBatch(BatchBuilder $batch_definition): void {
    // Set and configure the batch.
    batch_set($batch_definition->toArray());
    $batch = & batch_get();
    $batch['progressive'] = FALSE;

    // Process the batch.
    drush_backend_batch_process();
  }

}
