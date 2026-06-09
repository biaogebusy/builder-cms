<?php

declare(strict_types=1);

namespace Drupal\ai;

use Drupal\ai\Enum\VdbSimilarityMetrics;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\ai\Attribute\AiVdbProvider;
use Drupal\Core\Utility\Error;

/**
 * Vector DB plugin manager.
 */
final class AiVdbProviderPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/VdbProvider', $namespaces, $module_handler, AiVdbProviderInterface::class, AiVdbProvider::class);
    $this->alterInfo('ai_vdb_provider_info');
    $this->setCacheBackend($cache_backend, 'ai_vdb_provider_info_plugins');
  }

  /**
   * Creates a plugin instance of a Vector Database Provider.
   *
   * @param string $plugin_id
   *   The ID of the plugin being instantiated.
   * @param array $configuration
   *   An array of configuration relevant to the plugin instance.
   *
   * @return \Drupal\ai\Attribute\AiVdbProviderInterface
   *   A fully configured vector database plugin instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function createInstance($plugin_id, array $configuration = []): AiVdbProviderInterface {
    /** @var \Drupal\ai\AiVdbProviderInterface $providerInstance */
    $providerInstance = parent::createInstance($plugin_id, $configuration);
    return $providerInstance;
  }

  /**
   * Gets all the available Vector DB providers.
   *
   * @param bool $setup
   *   If TRUE, only return the providers that are setup.
   *
   * @return array
   *   The providers.
   */
  public function getProviders($setup = FALSE): array {
    $plugins = [];
    foreach ($this->getDefinitions() as $definition) {
      $instance = $this->createInstance($definition['id']);
      if ($setup && !$instance->isSetup()) {
        continue;
      }
      $plugins[$definition['id']] = $definition['label']->__toString();
    }
    return $plugins;
  }

  /**
   * Gets the available Vector DB providers that support Search API.
   *
   * @param bool $setup
   *   If TRUE, only return the providers that are setup.
   *
   * @return array
   *   The providers.
   */
  public function getSearchApiProviders($setup = FALSE): array {
    $plugins = [];
    if (!interface_exists('\Drupal\ai_search\AiVdbProviderSearchApiInterface')) {
      return [];
    }
    foreach ($this->getDefinitions() as $definition) {
      $instance = $this->createInstance($definition['id']);
      if ($setup && !$instance->isSetup()) {
        continue;
      }

      // Ignore this line since AI Search submodule may not be
      // enabled, so we need to use the full namespace, but we bailed
      // early in this method if the class does not yet exist.
      // phpcs:ignore
      if (!$instance instanceof \Drupal\ai_search\AiVdbProviderSearchApiInterface) {
        continue;
      }
      $plugins[$definition['id']] = $definition['label']->__toString();
    }
    return $plugins;
  }

  /**
   * Ensure that the collection exists and can be successfully accessed.
   *
   * It will attempt to create the collection if it does not exist.
   *
   * @param \Drupal\ai\AiVdbProviderInterface $provider
   *   The provider.
   * @param array $configuration
   *   The configuration to validate against.
   *
   * @return bool
   *   Whether the collection exists.
   */
  public function ensureCollectionExists(AiVdbProviderInterface $provider, array $configuration) {
    try {
      // Check if the collection exists.
      $collections = $provider->getCollections($configuration['database_settings']['database_name']);
      if (is_array($collections) && in_array($configuration['database_settings']['collection'], $collections)) {
        return TRUE;
      }

      // Check if the metric is defined, if not, use cosine similarity.
      if (isset($configuration['database_settings']['metric'])) {
        $metric = VdbSimilarityMetrics::from($configuration['database_settings']['metric']);
      }
      else {
        $metric = VdbSimilarityMetrics::CosineSimilarity;
      }

      // Otherwise create the collection.
      $provider->createCollection(
        $configuration['database_settings']['collection'],
        (int) $configuration['embeddings_engine_configuration']['dimensions'],
        $metric,
        $configuration['database_settings']['database_name'],
      );
      return TRUE;
    }
    catch (\Exception $exception) {
      /** @var \Psr\Log\LoggerInterface $logger */
      // phpcs:disable
      // @phpstan-ignore-next-line
      $logger = \Drupal::logger('ai_search');
      // phpcs:enable
      Error::logException($logger, $exception, '%type: @message in %function (line %line of %file).');
      return FALSE;
    }
  }

}
