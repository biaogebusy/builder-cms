<?php

namespace Drupal\advancedqueue\Hook;

use Drupal\advancedqueue\Entity\QueueInterface;
use Drupal\advancedqueue\ProcessorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for advancedqueue.
 */
class AdvancedqueueHooks {

  public function __construct(
    private readonly ProcessorInterface $processor,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    $queue_storage = $this->entityTypeManager->getStorage('advancedqueue_queue');
    /** @var \Drupal\advancedqueue\Entity\QueueInterface[] $queues */
    $queues = $queue_storage->loadByProperties([
      'processor' => QueueInterface::PROCESSOR_CRON,
    ]);
    foreach ($queues as $queue) {
      $this->processor->processQueue($queue);
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'advancedqueue_state_icon' => [
        'variables' => [
          'state' => NULL,
        ],
      ],
    ];
  }

}
