<?php

declare(strict_types=1);

namespace Drupal\ai_search;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\ai_search\Attribute\EmbeddingStrategy;

/**
 * Embedding strategy plugin manager.
 */
final class EmbeddingStrategyPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/EmbeddingStrategy',
      $namespaces,
      $module_handler,
      EmbeddingStrategyInterface::class,
      EmbeddingStrategy::class
    );
    $this->setCacheBackend($cache_backend, 'ai_embedding_strategy_plugins');
    $this->alterInfo('embedding_strategy_info');
  }

  /**
   * Create an embedding strategy instance.
   *
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $configuration
   *   The configuration for the plugin.
   *
   * @return \Drupal\ai_search\Attribute\EmbeddingStrategyInterface
   *   The embedding strategy.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *    A plugin exception.
   */
  public function createInstance($plugin_id, array $configuration = []): EmbeddingStrategyInterface {
    /** @var \Drupal\ai_search\EmbeddingStrategyInterface $embedding_strategy */
    $embedding_strategy = parent::createInstance($plugin_id, $configuration);
    return $embedding_strategy;
  }

  /**
   * Gets all the available Embedding Strategies.
   *
   * @return array
   *   The strategies.
   */
  public function getStrategies(): array {
    $plugins = [];
    foreach ($this->getDefinitions() as $definition) {
      $plugins[$definition['id']] = $definition['label']->__toString();
    }
    return $plugins;
  }

  /**
   * Gets all the available Embedding Strategies.
   *
   * @return array
   *   The strategies.
   */
  public function getStrategyDetails(): array {
    $plugins = [];
    foreach ($this->getDefinitions() as $definition) {
      $plugins[$definition['id']] = (array) $definition;
    }
    return $plugins;
  }

}
