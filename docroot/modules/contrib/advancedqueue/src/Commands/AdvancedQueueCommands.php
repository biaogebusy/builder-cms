<?php

namespace Drupal\advancedqueue\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\ProcessorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drush\Commands\DrushCommands;

/**
 * Declares AdvancedQueue module Drush commands.
 */
class AdvancedQueueCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The queue processor.
   *
   * @var \Drupal\advancedqueue\ProcessorInterface
   */
  protected $processor;

  /**
   * Constructs a new AdvancedQueueCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\advancedqueue\ProcessorInterface $processor
   *   The queue processor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ProcessorInterface $processor) {
    parent::__construct();

    $this->entityTypeManager = $entity_type_manager;
    $this->processor = $processor;
  }

  /**
   * Process a queue.
   *
   * @param string $queue_id
   *   The queue ID.
   * @param array $options
   *   The options passed to this drush function.
   *
   * @throws \Exception
   *
   * @command advancedqueue:queue:process
   * @option timeout The maximum execution time of the script. Be warned that this is a rough estimate as the time is only checked between two items.
   * @usage advancedqueue:queue:process queuename --timeout=60
   *   Set maximum queue processing time to 60 seconds. After this, the process
   *   will complete even if there are items left in the queue.
   */
  public function process($queue_id, array $options = ['timeout' => 90]) {
    $queue_storage = $this->entityTypeManager->getStorage('advancedqueue_queue');
    /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
    $queue = $queue_storage->load($queue_id);
    if (!$queue) {
      throw new \Exception(dt('Could not find queue "@queue_id".', ['@queue_id' => $queue_id]));
    }

    if (extension_loaded('pcntl')) {
      pcntl_async_signals(TRUE);

      pcntl_signal(SIGTERM, function () {
        $this->processor->stop();
      });

      pcntl_signal(SIGINT, function () {
        $this->processor->stop();
      });
    }

    // Set the processing time for this Drush command. Note: it is up to
    // Processor implementations to handle this. See the default
    // \Drupal\advancedqueue\Processor class for an example of this.
    $queue->setProcessingTime((int) $options['timeout']);

    $start = microtime(TRUE);
    $num_processed = $this->processor->processQueue($queue);
    $elapsed = microtime(TRUE) - $start;

    $this->io()->success(dt('Processed @count jobs from the @queue queue in @elapsed seconds.', [
      '@count' => $num_processed,
      '@queue' => $queue->label(),
      '@elapsed' => round($elapsed, 2),
    ]));
  }

  /**
   * List queues.
   *
   * @field-labels
   *   id: ID
   *   label: Label
   *   jobs: Jobs
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   The output.
   *
   * @command advancedqueue:queue:list
   */
  public function listQueues() {
    $count_labels = [
      Job::STATE_QUEUED => new TranslatableMarkup('Queued'),
      Job::STATE_PROCESSING => new TranslatableMarkup('Processing'),
      Job::STATE_SUCCESS => new TranslatableMarkup('Success'),
      Job::STATE_FAILURE => new TranslatableMarkup('Failure'),
    ];

    $queue_storage = $this->entityTypeManager->getStorage('advancedqueue_queue');
    $rows = [];
    foreach ($queue_storage->loadMultiple() as $queue) {
      /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
      $jobs = [];
      foreach ($queue->getBackend()->countJobs() as $state => $count) {
        $jobs[] = sprintf('%s: %s', $count_labels[$state], $count);
      }

      $rows[] = [
        'id' => $queue->id(),
        'label' => $queue->label(),
        'jobs' => implode(' | ', $jobs),
      ];
    }

    return new RowsOfFields($rows);
  }

}
