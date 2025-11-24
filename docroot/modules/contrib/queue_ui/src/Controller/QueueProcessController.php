<?php

namespace Drupal\queue_ui\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\queue_ui\QueueUIBatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for the queue process route.
 *
 * @phpstan-consistent-constructor
 */
class QueueProcessController implements ContainerInjectionInterface {

  /**
   * Constructor for QueueProcessController.
   *
   * @param \Drupal\queue_ui\QueueUIBatchInterface $batch
   *   Queue UI batch instance.
   */
  public function __construct(
    protected QueueUIBatchInterface $batch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('queue_ui.batch'));
  }

  /**
   * Process a certain queue.
   */
  public function process(string $queueName): ?RedirectResponse {
    $this->batch->batch([$queueName]);

    return batch_process('<front>');
  }

  /**
   * Checks access for processing a certain queue.
   */
  public function access(AccountProxyInterface $account, string $queueName): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, sprintf('process %s queue', $queueName));
  }

}
