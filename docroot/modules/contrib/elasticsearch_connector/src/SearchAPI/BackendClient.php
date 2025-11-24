<?php

namespace Drupal\elasticsearch_connector\SearchAPI;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Utility\Error;
use Drupal\elasticsearch_connector\Analyser\AnalyserInterface;
use Drupal\elasticsearch_connector\Analyser\AnalyserManager;
use Drupal\elasticsearch_connector\Event\AlterSettingsEvent;
use Drupal\elasticsearch_connector\Event\IndexCreatedEvent;
use Drupal\elasticsearch_connector\SearchAPI\Query\QueryParamBuilder;
use Drupal\elasticsearch_connector\SearchAPI\Query\QueryResultParser;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Transport\Exception\TransportException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides an ElasticSearch Search API client.
 */
class BackendClient implements BackendClientInterface {

  use DependencySerializationTrait {
    __sleep as traitSleep;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable() {
    // @todo If https://github.com/elastic/elasticsearch-php/issues/1308 gets
    // fixed, we could provide more information about the error.
    try {
      $this->client->ping();
      return TRUE;
    }
    catch (ElasticSearchException $e) {
      $this->logger->error('%type: @message in %function (line %line of %file).', Error::decodeException($e));
      return FALSE;
    }
    catch (\Exception $e) {
      $this->logger->error('%type: @message in %function (line %line of %file).', Error::decodeException($e));
      return FALSE;
    }
  }

  /**
   * Constructs a new BackendClient.
   *
   * @param \Drupal\elasticsearch_connector\SearchAPI\Query\QueryParamBuilder $queryParamBuilder
   *   The query param builder.
   * @param \Drupal\elasticsearch_connector\SearchAPI\Query\QueryResultParser $resultParser
   *   The query result parser.
   * @param \Drupal\elasticsearch_connector\SearchAPI\DeleteParamBuilder $deleteParamBuilder
   *   The delete param builder.
   * @param \Drupal\elasticsearch_connector\SearchAPI\IndexParamBuilder $indexParamBuilder
   *   The index param builder.
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fieldsHelper
   *   The fields helper.
   * @param \Drupal\elasticsearch_connector\SearchAPI\FieldMapper $fieldParamsBuilder
   *   THe field mapper.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Elastic\Elasticsearch\Client $client
   *   The ElasticSearch client.
   * @param \Drupal\elasticsearch_connector\Analyser\AnalyserManager $analyserManager
   *   Analyser manager.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param array $settings
   *   The settings.
   */
  public function __construct(
    protected QueryParamBuilder $queryParamBuilder,
    protected QueryResultParser $resultParser,
    protected DeleteParamBuilder $deleteParamBuilder,
    protected IndexParamBuilder $indexParamBuilder,
    protected FieldsHelperInterface $fieldsHelper,
    protected FieldMapper $fieldParamsBuilder,
    protected LoggerInterface $logger,
    protected Client $client,
    protected AnalyserManager $analyserManager,
    protected EventDispatcherInterface $eventDispatcher,
    protected array $settings = [],
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(IndexInterface $index, array $items): array {
    if (empty($items)) {
      return [];
    }
    $indexId = $this->getIndexId($index);

    $params = $this->indexParamBuilder->buildIndexParams($indexId, $index, $items);

    try {
      $response = $this->client->bulk($params);
      // If there were any errors, log them and throw an exception.
      if (!empty($response['errors'])) {
        foreach ($response['items'] as $item) {
          if (!empty($item['index']['status']) && $item['index']['status'] == '400') {
            $this->logger->error('%reason. %caused_by for index: %id', [
              '%reason' => $item['index']['error']['reason'],
              '%caused_by' => $item['index']['error']['caused_by']['reason'] ?? '',
              '%id' => $item['index']['_id'],
            ]);
          }
        }
        throw new SearchApiException('An error occurred indexing items.');
      }
    }
    catch (ElasticSearchException | TransportException $e) {
      throw new SearchApiException(sprintf('%s when indexing items in index %s.', $e->getMessage(), $indexId), 0, $e);
    }

    return array_keys($items);

  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index, array $item_ids): void {
    if (empty($item_ids)) {
      return;
    }

    $indexId = $this->getIndexId($index);
    $params = $this->deleteParamBuilder->buildDeleteParams($indexId, $item_ids);
    try {
      $this->client->bulk($params);
    }
    catch (ElasticSearchException | TransportException $e) {
      throw new SearchApiException(sprintf('An error occurred deleting items from the index %s.', $indexId), 0, $e);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query): ResultSetInterface {
    $resultSet = $query->getResults();
    $index = $query->getIndex();
    $indexId = $this->getIndexId($index);
    $params = [
      'index' => $indexId,
    ];
    try {
      // Check index exists.
      if (!$this->client->indices()->exists($params)) {
        $this->logger->warning('Index "%index" does not exist.', ["%index" => $indexId]);
        return $resultSet;
      }
    }
    catch (\Exception $e) {
      throw new SearchApiException(sprintf('Error: %s', $e->getMessage()), 0, $e);
    }

    // Build ElasticSearch query.
    $params = $this->queryParamBuilder->buildQueryParams($indexId, $query, $this->settings);

    try {

      // When set to true the search response will always track the number of
      // hits that match the query accurately.
      $params['track_total_hits'] = TRUE;

      // Do search.
      $response = $this->client->search($params);
      $resultSet = $this->resultParser->parseResult($query, $response->asArray());

      return $resultSet;
    }
    catch (ElasticSearchException | TransportException $e) {
      throw new SearchApiException(sprintf('Error querying index %s', $indexId), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeIndex($index): void {
    if (!$this->indexExists($index)) {
      return;
    }
    $indexId = $this->getIndexId($index);
    try {
      $this->client->indices()->delete([
        'index' => [$indexId],
      ]);
    }
    catch (ElasticSearchException | TransportException $e) {
      throw new SearchApiException(sprintf('An error occurred removing the index %s.', $indexId), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addIndex(IndexInterface $index): void {
    $indexId = $this->getIndexId($index);
    if ($this->indexExists($index)) {
      return;
    }

    try {
      $this->client->indices()->create([
        'index' => $indexId,
      ]);
      $this->updateSettings($index);
      $this->updateFieldMapping($index);
      $event = new IndexCreatedEvent($index);
      $this->eventDispatcher->dispatch($event);
    }
    catch (ElasticSearchException | TransportException $e) {
      throw new SearchApiException(sprintf('An error occurred creating the index %s.', $indexId), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateIndex(IndexInterface $index): void {
    // If the index does not exist, create it with the current settings, then
    // return.
    if (!$this->indexExists($index)) {
      $this->addIndex($index);
      return;
    }

    // If we get here, the index exists, so we need to update it.
    // If the change requires us to rebuild the index, clear the index first.
    if ($this->indexChangeRequiresRebuild($index)) {
      $index->clear();
    }
    $this->updateSettings($index);
    $this->updateFieldMapping($index);
  }

  /**
   * Updates the field mappings for an index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown when an underlying ElasticSearch error occurs.
   */
  public function updateFieldMapping(IndexInterface $index): void {
    $indexId = $this->getIndexId($index);
    try {
      $params = $this->fieldParamsBuilder->mapFieldParams($indexId, $index);
      $this->client->indices()->putMapping($params);
    }
    catch (ElasticSearchException | TransportException $e) {
      throw new SearchApiException(sprintf('An error occurred updating field mappings for index %s.', $indexId), 0, $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clearIndex(IndexInterface $index, ?string $datasource_id = NULL): void {
    $this->removeIndex($index);
    $this->addIndex($index);
  }

  /**
   * {@inheritdoc}
   */
  public function indexExists(IndexInterface $index): bool {
    $indexId = $this->getIndexId($index);
    try {
      return $this->client->indices()->exists([
        'index' => $indexId,
      ])->asBool();
    }
    catch (ElasticSearchException | TransportException $e) {
      throw new SearchApiException(sprintf('An error occurred checking if the index %s exists.', $indexId), 0, $e);
    }
  }

  /**
   * Gets the index ID.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   *
   * @return string
   *   The index ID.
   */
  public function getIndexId(IndexInterface $index) {
    return $this->settings['prefix'] . $index->id() . $this->settings['suffix'];
  }

  /**
   * {@inheritdoc}
   *
   * Make sure that the client does not get serialized.
   */
  public function __sleep() {
    $vars = $this->traitSleep();
    unset($vars[array_search('client', $vars)]);
    return $vars;
  }

  /**
   * Updates index settings.
   *
   * @param \Drupal\search_api\IndexInterface $index_param
   *   Index.
   */
  public function updateSettings(IndexInterface $index_param): void {
    $indexId = $this->getIndexId($index_param);
    $params = $this->fieldParamsBuilder->mapFieldParams($indexId, $index_param);
    $analysers = array_reduce($params['body']['properties'], function (array $carry, array $field_definition) {
      if (isset($field_definition['analyser'])) {
        $carry[$field_definition['analyser']] = $field_definition['analyser_settings'] ?? [];
      }
      return $carry;
    }, []);
    $settings = [];
    foreach ($analysers as $analyser_id => $configuration) {
      $analyser = $this->analyserManager->createInstance($analyser_id, $configuration);
      assert($analyser instanceof AnalyserInterface);
      $settings = NestedArray::mergeDeep($settings, $analyser->getSettings());
    }

    $backendConfig = $index_param->getServerInstance()->getBackendConfig();

    $event = new AlterSettingsEvent($settings, $backendConfig, $index_param);
    $this->eventDispatcher->dispatch($event);
    $settings = $event->getSettings();

    if (!$settings) {
      // Nothing to push.
      return;
    }
    try {
      $this->client->indices()->putSettings([
        'index' => $indexId,
        'reopen' => TRUE,
        'body' => $settings,
      ]);
    }
    catch (ElasticSearchException | TransportException $e) {
      throw new SearchApiException(sprintf('An error occurred updating settings for index %s.', $indexId), 0, $e);
    }
  }

  /**
   * Determine if a pending change to an index requires the index to be rebuilt.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index to examine.
   *
   * @return bool
   *   TRUE if the pending change requires the index to be rebuilt/cleared;
   *   FALSE if it does not.
   */
  public function indexChangeRequiresRebuild(IndexInterface $index): bool {
    $oldIndex = $index->original ?? Index::create();
    $newIndex = $index;
    $oldFields = $oldIndex->getFields();
    $newFields = $newIndex->getFields();

    // If we find any fields that were in the old index, but are not in the new
    // index, then those fields have been removed, so we need to rebuild the
    // index.
    $fieldsRemovedInNewIndex = \array_diff(\array_keys($oldFields), \array_keys($newFields));
    if (!empty($fieldsRemovedInNewIndex)) {
      return TRUE;
    }

    // If we find any fields in the new index whose data type is different from
    // the old index, then the mapping of the field has changed, so we need to
    // rebuild the index.
    foreach ($newFields as $newFieldName => $newField) {
      $oldField = $oldFields[$newFieldName] ?? NULL;

      // ElasticSearch can handle fields being added without rebuilding the
      // index, so if $oldField is NULL, then skip the rest of this loop and
      // move on to the next field.
      if (\is_null($oldField)) {
        continue;
      }

      // If a field's data type has changed, then we've changed the mapping of
      // the field, so need to rebuild the index.
      if ($oldField->getType() !== $newField->getType()) {
        return TRUE;
      }
    }

    // If we get here, then we haven't found any changes that would require a
    // rebuild, so return FALSE.
    return FALSE;
  }

}
