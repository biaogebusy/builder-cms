<?php

namespace Drupal\elasticsearch_connector\SearchAPI\Query;

use Drupal\search_api\Query\QueryInterface;

/**
 * Provides a spell check builder.
 */
class SpellCheckBuilder {

  /**
   * Set up the SpellCheck clause of the Elasticsearch query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   *
   * @return array
   *   Array of suggester query.
   *
   * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-suggesters.html
   */
  public function setSpellCheckQuery(QueryInterface $query): array {
    $suggester_query = [];
    $options = $query->getOption('search_api_spellcheck');
    if (isset($options['keys']) && isset($options['count'])) {
      $terms = implode(' ', $options['keys']);
      foreach ($this->getFulltextFields($query) as $field_name) {
        $suggester_query[$field_name] = [
          'text' => $terms,
          'term' => [
            'field' => $field_name,
            'size' => $options['count'],
          ],
        ];
      }
    }
    return $suggester_query;
  }

  /**
   * Get the full text fields for this search.
   *
   * QueryInterface::getFulltextFields will return NULL if all indexed fulltext
   * fields should be used. In that case, we get the full text fields from the
   * index.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   *
   * @return string[]
   *   Array of the fulltext fields that will be searched by this query.
   */
  private function getFullTextFields(QueryInterface $query): array {
    $fullTextFields = $query->getFulltextFields();
    if (is_null($fullTextFields)) {
      $fullTextFields = $query->getIndex()->getFulltextFields();
    }
    return $fullTextFields;
  }

}
