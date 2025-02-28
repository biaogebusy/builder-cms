<?php

namespace Drupal\advancedqueue;

use Drupal\advancedqueue\Annotation\AdvancedQueueJobType;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages job type plugins.
 */
class JobTypeManager extends DefaultPluginManager {

  /**
   * Constructs a new JobTypeManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AdvancedQueue/JobType', $namespaces, $module_handler, JobTypeInterface::class, AdvancedQueueJobType::class);

    $this->alterInfo('advancedqueue_job_type_info');
    $this->setCacheBackend($cache_backend, 'advancedqueue_job_type_plugins');
  }

}
