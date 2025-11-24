<?php

namespace Drupal\queue_ui;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;

/**
 * Defines the queue worker manager.
 *
 * @see \Drupal\Core\Queue\QueueWorkerInterface
 * @see \Drupal\Core\Queue\QueueWorkerBase
 * @see \Drupal\Core\Annotation\QueueWorker
 * @see plugin_api
 */
class QueueUIManager extends DefaultPluginManager {

  /**
   * Constructs an QueueWorkerManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Queue\QueueFactory $queueService
   *   The queue service.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    protected QueueFactory $queueService,
  ) {
    parent::__construct('Plugin/QueueUI', $namespaces, $module_handler, 'Drupal\queue_ui\QueueUIInterface', 'Drupal\queue_ui\Annotation\QueueUI');

    $this->setCacheBackend($cache_backend, 'queue_ui_plugins');
    $this->alterInfo('queue_ui_info');
  }

  /**
   * Queue name.
   *
   * @param string $queueName
   *   The name of the queue being inspected.
   *
   * @return false|object
   *   An object of queue class name
   */
  public function fromQueueName(string $queueName): object|false {
    $queue = $this->queueService->get($queueName);

    try {
      foreach ($this->getDefinitions() as $definition) {
        if ($definition['class_name'] === $this->queueClassName($queue)) {
          return $this->createInstance($definition['id']);
        }
      }
    }
    catch (\Exception $e) {
    }

    return FALSE;
  }

  /**
   * Get the queue class name.
   *
   * @var \Drupal\Core\Queue\QueueInterface $queue
   *   An array of queue information.
   *
   * @return string|null
   *   A mixed value of queue class
   */
  public function queueClassName(QueueInterface $queue): ?string {
    $namespace = explode('\\', get_class($queue));
    return array_pop($namespace);
  }

}
