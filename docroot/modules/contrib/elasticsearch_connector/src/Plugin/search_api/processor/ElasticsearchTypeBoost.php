<?php

namespace Drupal\elasticsearch_connector\Plugin\search_api\processor;

use Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Plugin\search_api\processor\TypeBoost;
use Drupal\search_api\Query\QueryInterface;

/**
 * Adds a query time boost to items based on their datasource and/or bundle.
 *
 * @SearchApiProcessor(
 *   id = "elasticsearch_type_boost",
 *   label = @Translation("Type-specific boosting (Elasticsearch)"),
 *   description = @Translation("Adds a query-time boost to items based on their datasource and/or bundle."),
 *   stages = {
 *     "preprocess_query" = -10,
 *   }
 * )
 */
class ElasticsearchTypeBoost extends TypeBoost {

  use PluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {
    $config = $this->configuration['boosts'];
    $type_boost_functions = [];

    foreach ($config as $key => $datasource_config) {
      if (isset($datasource_config['bundle_boosts'])) {
        foreach ($datasource_config['bundle_boosts'] as $type => $weight) {
          if (!empty($weight)) {
            $type_boost_functions[] = [
              'filter' => ['match' => ['type' => $type]],
              'weight' => (float) $weight,
            ];
          }
          elseif (!empty($datasource_config['datasource_boost'])) {
            $type_boost_functions[] = [
              'filter' => ['match' => ['type' => $type]],
              'weight' => (float) $datasource_config['datasource_boost'],
            ];
          }
        }
      }
      elseif (!empty($datasource_config['datasource_boost'])) {
        $entity_type_id = explode(':', $key)[1] ?? $key;
        $type_boost_functions[] = [
          'filter' => ['match' => ['search_api_datasource' => $key]],
          'weight' => (float) $datasource_config['datasource_boost'],
        ];
      }
    }

    if (!empty($type_boost_functions)) {
      $query->setOption(
        'elasticsearch_connector_type_boost_functions',
        $type_boost_functions
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    $server = $index->getServerInstance();
    return $server && $server->getBackend() instanceof ElasticSearchBackend;
  }

}
