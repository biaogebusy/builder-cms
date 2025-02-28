<?php

namespace Drupal\queue_ui;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Queue\QueueFactory;

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
   * The queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueService;

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
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   The queue service.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, QueueFactory $queue) {
    parent::__construct('Plugin/QueueUI', $namespaces, $module_handler, 'Drupal\queue_ui\QueueUIInterface', 'Drupal\queue_ui\Annotation\QueueUI');

    $this->setCacheBackend($cache_backend, 'queue_ui_plugins');
    $this->alterInfo('queue_ui_info');
    $this->queueService = $queue;
  }

  /**
   * Queue name.
   *
   * @param string $queueName
   *   The name of the queue being inspected.
   *
   * @return bool|object
   *   An object of queue class name
   */
  public function fromQueueName($queueName) {
    $queue = $this->queueService->get($queueName);

    try {
      foreach ($this->getDefinitions() as $definition) {
        if ($definition['class_name'] == $this->queueClassName($queue)) {
          return parent::createInstance($definition['id']);
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
   * @var array $queue
   *   An arrayof queue information.
   *
   * @return mixed
   *   A mixed value of queue class
   */
  public function queueClassName($queue) {
    $namespace = explode('\\', get_class($queue));
    return array_pop($namespace);
  }

}
