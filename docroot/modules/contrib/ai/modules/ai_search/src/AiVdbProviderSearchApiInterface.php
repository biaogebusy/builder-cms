<?php

namespace Drupal\ai_search;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;

/**
 * Defines an interface for Search API VDB (Vector Database) providers.
 *
 * All VDB Providers must also implement AiVdbProviderInterface, but this
 * interface also makes the provider available for use as a Search API backend.
 */
interface AiVdbProviderSearchApiInterface extends PluginInspectionInterface {

  /**
   * Build the settings form for this vector database provider.
   *
   * @param array $form
   *   The form from SearchApiAISearchBackend.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state from SearchApiAISearchBackend.
   * @param array $configuration
   *   The SearchApiAISearchBackend complete configuration.
   *
   * @return array
   *   The updated sub-form.
   */
  public function buildSettingsForm(
    array $form,
    FormStateInterface $form_state,
    array $configuration,
  ): array;

  /**
   * Validate the selected vector database settings.
   *
   * @param array $form
   *   The SearchApiAISearchBackend form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The SearchApiAISearchBackend form state.
   */
  public function validateSettingsForm(array &$form, FormStateInterface $form_state): void;

  /**
   * Submit the selected vector database settings.
   *
   * @param array $form
   *   The SearchApiAISearchBackend form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The SearchApiAISearchBackend form state.
   */
  public function submitSettingsForm(array &$form, FormStateInterface $form_state): void;

  /**
   * Index details to display on the View Search API index page.
   *
   * @param array $database_settings
   *   The database settings configuration.
   *
   * @return array
   *   An array of settings with nested keys 'label' and 'info' as expected by
   *   search_api.theme.inc.
   */
  public function viewIndexSettings(array $database_settings): array;

  /**
   * The ::indexItems() method from Search API is passed on to here.
   *
   * This allows the VDB Provider to index items in its own way. For example
   * Milvus expects to drop items and then add new items on update. Pinecone
   * however has an update option which is more efficient for it.
   *
   * @param array $configuration
   *   The Search API configuration.
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index.
   * @param array $items
   *   The items to index.
   * @param \Drupal\ai_search\EmbeddingStrategyInterface $embedding_strategy
   *   The embedding strategy.
   *
   * @return array
   *   The successfully indexed item IDs.
   */
  public function indexItems(
    array $configuration,
    IndexInterface $index,
    array $items,
    EmbeddingStrategyInterface $embedding_strategy,
  ): array;

  /**
   * Delete items by ID from the vector database index.
   *
   * @param array $configuration
   *   The configuration from SearchApiAISearchBackend.
   * @param \Drupal\search_api\IndexInterface $index
   *   The index being operated on from SearchApiAISearchBackend.
   * @param array $item_ids
   *   The Drupal IDs to be deleted.
   */
  public function deleteIndexItems(
    array $configuration,
    IndexInterface $index,
    array $item_ids,
  ): void;

  /**
   * Delete all items from the vector database index.
   *
   * @param array $configuration
   *   The configuration from SearchApiAISearchBackend.
   * @param \Drupal\search_api\IndexInterface $index
   *   The index being operated on from SearchApiAISearchBackend.
   * @param mixed $datasource_id
   *   The datasource ID from SearchApiAISearchBackend.
   */
  public function deleteAllIndexItems(
    array $configuration,
    IndexInterface $index,
    $datasource_id = NULL,
  ): void;

  /**
   * Prepare the filters for the specific VDB Provider.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API Query.
   *
   * @return mixed
   *   The filters as expected by the VDB Provider.
   */
  public function prepareFilters(
    QueryInterface $query,
  ): mixed;

}
