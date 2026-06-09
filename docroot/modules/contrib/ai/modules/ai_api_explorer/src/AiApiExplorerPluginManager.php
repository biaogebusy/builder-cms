<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\ai_api_explorer\Attribute\AiApiExplorer;

/**
 * AiApiExplorer plugin manager.
 */
final class AiApiExplorerPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AiApiExplorer', $namespaces, $module_handler, AiApiExplorerInterface::class, AiApiExplorer::class);
    $this->alterInfo('ai_api_explorer_info');
    $this->setCacheBackend($cache_backend, 'ai_api_explorer_plugins');
  }

}
