<?php

namespace Drupal\elasticsearch_connector\SearchAPI\Query;

use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;

/**
 * Provides a result set parser.
 */
class QueryResultParser {

  /**
   * Creates a new QueryResultParser.
   *
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fieldsHelper
   *   The fields helper.
   * @param \Drupal\elasticsearch_connector\SearchAPI\Query\FacetResultParser $facetResultParser
   *   The facet result parser.
   * @param \Drupal\elasticsearch_connector\SearchAPI\Query\SpellCheckResultParser $spellCheckResultParser
   *   The spellcheck result parser.
   */
  public function __construct(
    protected FieldsHelperInterface $fieldsHelper,
    protected FacetResultParser $facetResultParser,
    protected SpellCheckResultParser $spellCheckResultParser,
  ) {
  }

  /**
   * Parse a ElasticSearch response into a ResultSetInterface.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   Search API query.
   * @param array $response
   *   Raw response array back from ElasticSearch.
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   *   The results of the search.
   */
  public function parseResult(QueryInterface $query, array $response): ResultSetInterface {
    $index = $query->getIndex();

    // Set up the results array.
    $results = $query->getResults();
    $results->setExtraData('elasticsearch_response', $response);
    $results->setResultCount($response['hits']['total']['value']);
    // Add each search result to the results array.
    if (!empty($response['hits']['hits'])) {
      foreach ($response['hits']['hits'] as $result) {
        $result_item = $this->fieldsHelper->createItem($index, $result['_id']);
        $result_item->setScore($result['_score']);

        // Set each item in _source as a field in Search API.
        foreach ($result['_source'] as $id => $values) {
          // Make everything a multi-field.
          if (!is_array($values)) {
            $values = [$values];
          }
          $field = $this->fieldsHelper->createField($index, $id, ['property_path' => $id]);
          $field->setValues($values);
          $result_item->setField($id, $field);
        }

        // If we see that ElasticSearch highlighted matching excerpts in the
        // search results, then store those matches in the result set so that
        // ElasticsearchHighlighter can process them.
        if (isset($result['highlight'])) {
          $result_item->setExtraData('highlight', $result['highlight']);
        }

        $results->addResultItem($result_item);
      }
    }

    if (!empty($response['aggregations'])) {
      $facets = $this->facetResultParser->parseFacetResult($query, $response);
      $results->setExtraData('search_api_facets', $facets);
    }

    if (!empty($response['suggest'])) {
      $candidates = $this->spellCheckResultParser->parseSpellCheckResult($query, $response);
      // Set under the search_api_spellcheck->suggestions key.
      $results->setExtraData('search_api_spellcheck', [
        'suggestions' => $candidates,
      ]);
    }

    return $results;
  }

}
