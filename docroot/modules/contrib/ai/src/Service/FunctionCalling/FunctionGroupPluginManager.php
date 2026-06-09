<?php

declare(strict_types=1);

namespace Drupal\ai\Service\FunctionCalling;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\ai\Attribute\FunctionGroup;

/**
 * Function call plugin manager.
 */
final class FunctionGroupPluginManager extends DefaultPluginManager {

  /**
   * The action manager.
   *
   * @var \Drupal\Core\Action\ActionManager
   */
  protected $actionManager;

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AiFunctionGroup', $namespaces, $module_handler, FunctionGroupInterface::class, FunctionGroup::class);
    $this->alterInfo('ai_function_group_info');
    $this->setCacheBackend($cache_backend, 'ai_function_group_info_plugins');
  }

}
