<?php

namespace Drupal\ai_search;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\ai\AiVdbProviderInterface;
use Drupal\ai\Enum\EmbeddingStrategyCapability;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;

/**
 * Embedding strategy is algorithm by which embedding happens.
 */
interface EmbeddingStrategyInterface extends PluginInspectionInterface {

  /**
   * Returns array of vectors for given main content and contextual content.
   *
   * Depending on the strategy, one or more vectors are returned in an array.
   *
   * @param string $embedding_engine
   *   The embedding engine.
   * @param string $chat_model
   *   The chat model ID for token calculations.
   * @param array $configuration
   *   The embedding strategy configuration.
   * @param array $fields
   *   The fields.
   * @param \Drupal\search_api\Item\ItemInterface $search_api_item
   *   The search API item.
   * @param \Drupal\search_api\IndexInterface $index
   *   The search API index.
   *
   * @return array<array{id: string, values: array, metadata: array}>
   *   The vectors.
   */
  public function getEmbedding(
    string $embedding_engine,
    string $chat_model,
    array $configuration,
    array $fields,
    ItemInterface $search_api_item,
    IndexInterface $index,
  ): array;

  /**
   * Not all the embedding strategies can be used with every Vector DB.
   *
   * This method returns TRUE if this strategy fits the given VDB
   * capabilities.
   *
   * @param \Drupal\ai\AiVdbProviderInterface $vdb_provider
   *   The VDB provider.
   *
   * @return bool
   *   TRUE if the strategy fits the VDB, FALSE otherwise.
   */
  public function fits(AiVdbProviderInterface $vdb_provider): bool;

  /**
   * Check whether an embedding strategy supports a capability.
   *
   * @param \Drupal\ai\Enum\EmbeddingStrategyCapability $capability
   *   The capability to check if supported.
   *
   * @return bool
   *   Whether the embedding strategy supports a particular capability.
   */
  public function supports(EmbeddingStrategyCapability $capability): bool;

  /**
   * Get the configuration subform for the Search API plugin embedding strategy.
   *
   * @param array $configuration
   *   The configuration.
   *
   * @return array
   *   The form API render array.
   */
  public function getConfigurationSubform(array $configuration): array;

  /**
   * Returns array of default configuration values for given model.
   *
   * @return array
   *   List of configuration values set for given model.
   */
  public function getDefaultConfigurationValues(): array;

}
