<?php

namespace Drupal\queue_ui_cron_test_fixtures\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a queue worker that processes cron test items.
 *
 * @QueueWorker(
 *   id = "queue_ui_cron_test",
 *   title = @Translation("Cron Test Queue"),
 *   cron = {"time" = 59}
 * )
 */
class Cron extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   *
   * @param array $configuration
   *   A configuration array.
   * @param string $plugin_id
   *   The plugin ID for the instance.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state interface.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected readonly StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $this->state->set('queue_ui_last_cron_test_worker_process_item', $data);
  }

}
