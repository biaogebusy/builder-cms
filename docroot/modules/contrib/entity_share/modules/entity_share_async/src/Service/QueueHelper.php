<?php

declare(strict_types = 1);

namespace Drupal\entity_share_async\Service;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;

/**
 * Populate a queue for asynchronous treatment.
 *
 * @package Drupal\entity_share_async\Service
 */
class QueueHelper implements QueueHelperInterface {

  /**
   * The queue factory service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * QueueHelper constructor.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    QueueFactory $queue_factory,
    StateInterface $state
  ) {
    $this->queueFactory = $queue_factory;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function enqueue($remote_id, $channel_id, $import_config_id, array $uuids) {
    $queue = $this->queueFactory->get(QueueHelperInterface::QUEUE_NAME);

    $async_states = $this->state->get(QueueHelperInterface::STATE_ID, []);

    foreach ($uuids as $uuid) {
      if (!isset($async_states[$remote_id][$channel_id][$uuid])) {
        // Add the entity to the async states.
        $async_states[$remote_id][$channel_id][$uuid] = $import_config_id;

        // Create queue item.
        $item = [
          'uuid' => $uuid,
          'remote_id' => $remote_id,
          'channel_id' => $channel_id,
          'import_config_id' => $import_config_id,
        ];

        $queue->createItem($item);
      }
    }

    // Update states.
    $this->state->set(QueueHelperInterface::STATE_ID, $async_states);
  }

}
