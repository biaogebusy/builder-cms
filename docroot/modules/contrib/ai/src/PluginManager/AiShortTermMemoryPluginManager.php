<?php

declare(strict_types=1);

namespace Drupal\ai\PluginManager;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\ai\Attribute\AiShortTermMemory;
use Drupal\ai\Base\AiShortTermMemoryPluginBase;
use Drupal\ai\Plugin\AiShortTermMemory\AiShortTermMemoryInterface;

/**
 * AiShortTermMemory plugin manager.
 */
final class AiShortTermMemoryPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AiShortTermMemory', $namespaces, $module_handler, AiShortTermMemoryInterface::class, AiShortTermMemory::class);
    $this->alterInfo('ai_short_term_memory_info');
    $this->setCacheBackend($cache_backend, 'ai_short_term_memory_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    // Run the parent process definition.
    parent::processDefinition($definition, $plugin_id);

    // Ensure that the plugins uses the base class.
    // This is to ensure that we control the processing of the short term
    // memory.
    assert(is_subclass_of($definition['class'], AiShortTermMemoryPluginBase::class), new PluginException(sprintf(
        'Plugin class %s for plugin ID %s must extend %s.',
        $definition['class'],
        $plugin_id,
        AiShortTermMemoryPluginBase::class
    )));
  }

}
