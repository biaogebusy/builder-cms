<?php

namespace Drupal\elasticsearch_connector\SearchAPI\Query;

use Drupal\search_api\Query\QueryInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides a query sort builder.
 */
class QuerySortBuilder {

  /**
   * Creates a new QuerySortBuilder.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * Helper function that returns sort for query in search.
   *
   * @return array
   *   Sort portion of the query.
   */
  public function getSortSearchQuery(QueryInterface $query): array {
    $index = $query->getIndex();
    $index_fields = $index->getFields();
    $sort = [];
    $query_full_text_fields = $index->getFulltextFields();
    foreach ($query->getSorts() as $field_id => $direction) {
      $direction = mb_strtolower($direction);

      if ($field_id === 'search_api_relevance') {
        // Apply only on fulltext search.
        $keys = $query->getKeys();
        if (!empty($keys)) {
          $sort['_score'] = $direction;
        }
      }
      elseif ($field_id === 'search_api_id') {
        $sort['id'] = $direction;
      }
      elseif ($field_id === '_id') {
        $sort['_id'] = $direction;
      }
      elseif (isset($index_fields[$field_id])) {
        if (in_array($field_id, $query_full_text_fields)) {
          // Set the field that has not been analyzed for sorting.
          $sort[$field_id . '.keyword'] = $direction;
        }
        else {
          $sort[$field_id] = $direction;
        }
      }
      else {
        $this->logger->warning(sprintf('Invalid sorting field: %s', $field_id));
      }

    }
    return $sort;
  }

}
