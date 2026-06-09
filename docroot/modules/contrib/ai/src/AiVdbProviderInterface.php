<?php

namespace Drupal\ai;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\ai\Enum\VdbSimilarityMetrics;
use Drupal\search_api\Query\QueryInterface;

/**
 * Defines an interface for All VDB (Vector Database) provider services.
 *
 * All VDB providers must implement this; however, the implementation of
 * AiVdbProviderSearchApiInterface is optional if the VDB provider is only
 * to be used independently of Search API.
 */
interface AiVdbProviderInterface extends PluginInspectionInterface {

  /**
   * Sets configuration of the database connection.
   *
   * @param array $config
   *   Configuration of client.
   */
  public function setCustomConfig(array $config): void;

  /**
   * Gets the configuration of the database.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The configuration.
   */
  public function getConfig(): ImmutableConfig;

  /**
   * Ping to check so the service/server is available.
   *
   * @return bool
   *   True if the service is available.
   */
  public function ping(): bool;

  /**
   * Checks if the service is setup.
   *
   * @return bool
   *   True if the service is setup.
   */
  public function isSetup(): bool;

  /**
   * Get array of existing collections on a database.
   *
   * @param string $database
   *   The database name.
   *
   * @return array
   *   Array of collection names.
   */
  public function getCollections(string $database = 'default'): array;

  /**
   * Creates a collection.
   *
   * @param string $collection_name
   *   The name of the collection.
   * @param int $dimension
   *   The dimension of the vectors.
   * @param \Drupal\ai\Enum\VdbSimilarityMetrics $metric_type
   *   The metric type.
   * @param string $database
   *   The database name.
   */
  public function createCollection(
    string $collection_name,
    int $dimension,
    VdbSimilarityMetrics $metric_type = VdbSimilarityMetrics::EuclideanDistance,
    string $database = 'default',
  ): void;

  /**
   * Drop collection from database.
   *
   * @param string $collection_name
   *   The name of the collection.
   * @param string $database
   *   The database name.
   */
  public function dropCollection(
    string $collection_name,
    string $database = 'default',
  ): void;

  /**
   * Insert record into collection.
   *
   * @param string $collection_name
   *   The name of the collection.
   * @param array $data
   *   The data to insert.
   * @param string $database
   *   The database name.
   */
  public function insertIntoCollection(
    string $collection_name,
    array $data,
    string $database = 'default',
  ): void;

  /**
   * Delete items by ID from the vector database index.
   *
   * @param array $configuration
   *   The configuration from SearchApiAISearchBackend.
   * @param array $item_ids
   *   The Drupal IDs to be deleted.
   */
  public function deleteItems(
    array $configuration,
    array $item_ids,
  ): void;

  /**
   * Delete all items from the vector database index.
   *
   * @param array $configuration
   *   The configuration from SearchApiAISearchBackend.
   * @param mixed $datasource_id
   *   The datasource ID from SearchApiAISearchBackend.
   */
  public function deleteAllItems(
    array $configuration,
    mixed $datasource_id = NULL,
  ): void;

  /**
   * Delete records from collection.
   *
   * @param string $collection_name
   *   The name of the collection.
   * @param array $ids
   *   The IDs to delete.
   * @param string $database
   *   The database name.
   */
  public function deleteFromCollection(
    string $collection_name,
    array $ids,
    string $database = 'default',
  ): void;

  /**
   * Conduct query search.
   *
   * @param string $collection_name
   *   The name of the collection.
   * @param array $output_fields
   *   The output fields.
   * @param mixed $filters
   *   The filters as prepared by the VDB provider in ::prepareFilters().
   * @param int $limit
   *   The limit.
   * @param int $offset
   *   The offset.
   * @param string $database
   *   The database name.
   *
   * @return array
   *   The results.
   */
  public function querySearch(
    string $collection_name,
    array $output_fields,
    string $filters = '',
    int $limit = 10,
    int $offset = 0,
    string $database = 'default',
  ): array;

  /**
   * Conduct vector search.
   *
   * @param string $collection_name
   *   The name of the collection.
   * @param array $vector_input
   *   The vector input.
   * @param array $output_fields
   *   The output fields.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   * @param mixed $filters
   *   The filters as prepared by the VDB provider in ::prepareFilters().
   * @param int $limit
   *   The limit.
   * @param int $offset
   *   The offset.
   * @param string $database
   *   The database name.
   *
   * @return array
   *   The results.
   */
  public function vectorSearch(
    string $collection_name,
    array $vector_input,
    array $output_fields,
    QueryInterface $query,
    string $filters = '',
    int $limit = 10,
    int $offset = 0,
    string $database = 'default',
  ): array;

  /**
   * Facade method to convert Drupal Entity IDs into Vector DB IDs.
   *
   * @param string $collection_name
   *   The name of the collection.
   * @param array $drupalIds
   *   The Drupal IDs.
   * @param string $database
   *   The database name.
   *
   * @return array
   *   The VDB IDs.
   */
  public function getVdbIds(
    string $collection_name,
    array $drupalIds,
    string $database = 'default',
  ): array;

  /**
   * Gets the field name used by this provider to store the embedding vector.
   *
   * Implementing this method signals that the provider can return raw
   * embedding vectors. If implemented, it should return the exact string name
   * of the field containing the vector (e.g., 'embedding', 'vector').
   * If the provider does not support returning raw vectors or uses a
   * different mechanism, it should return NULL.
   *
   * @return string|null
   *   The field name for the raw embedding vector, or NULL if not supported.
   */
  public function getRawEmbeddingFieldName(): ?string;

}
