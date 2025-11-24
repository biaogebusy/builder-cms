<?php

namespace Drupal\elasticsearch_connector\SearchAPI;

use Drupal\elasticsearch_connector\Analyser\AnalyserManager;
use Drupal\elasticsearch_connector\SearchAPI\Query\QueryParamBuilder;
use Drupal\elasticsearch_connector\SearchAPI\Query\QueryResultParser;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Elastic\Elasticsearch\Client;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a factory for creating a backend client.
 *
 * This is needed because the client is dynamically created based on the
 * connector plugin selected.
 */
class BackendClientFactory {

  /**
   * Creates a backend client factory.
   *
   * @param \Drupal\elasticsearch_connector\SearchAPI\Query\QueryParamBuilder $queryParamBuilder
   *   The query param builder.
   * @param \Drupal\elasticsearch_connector\SearchAPI\Query\QueryResultParser $resultParser
   *   The query result parser.
   * @param \Drupal\elasticsearch_connector\SearchAPI\DeleteParamBuilder $deleteParamBuilder
   *   The delete param builder.
   * @param \Drupal\elasticsearch_connector\SearchAPI\IndexParamBuilder $itemParamBuilder
   *   The index param builder.
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fieldsHelper
   *   The fields helper.
   * @param \Drupal\elasticsearch_connector\SearchAPI\FieldMapper $fieldParamsBuilder
   *   The field mapper.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\elasticsearch_connector\Analyser\AnalyserManager $analyserManager
   *   Analyser manager.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(
    protected QueryParamBuilder $queryParamBuilder,
    protected QueryResultParser $resultParser,
    protected DeleteParamBuilder $deleteParamBuilder,
    protected IndexParamBuilder $itemParamBuilder,
    protected FieldsHelperInterface $fieldsHelper,
    protected FieldMapper $fieldParamsBuilder,
    protected LoggerInterface $logger,
    protected AnalyserManager $analyserManager,
    protected EventDispatcherInterface $eventDispatcher,
  ) {
  }

  /**
   * Creates a new ElasticSearch Search API client.
   *
   * @param \Elastic\Elasticsearch\Client $client
   *   The ElasticSearch client.
   * @param array $settings
   *   THe backend settings.
   *
   * @return \Drupal\elasticsearch_connector\SearchAPI\BackendClientInterface
   *   The backend client.
   */
  public function create(Client $client, array $settings): BackendClientInterface {
    return new BackendClient(
      $this->queryParamBuilder,
      $this->resultParser,
      $this->deleteParamBuilder,
      $this->itemParamBuilder,
      $this->fieldsHelper,
      $this->fieldParamsBuilder,
      $this->logger,
      $client,
      $this->analyserManager,
      $this->eventDispatcher,
      $settings,
    );
  }

}
