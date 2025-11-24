<?php

namespace Drupal\elasticsearch_connector\SearchAPI\Query;

use Drupal\search_api\Query\QueryInterface;

/**
 * Provides a spell check result parser.
 */
class SpellCheckResultParser {

  /**
   * Parse the facet result.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   * @param array $response
   *   The response.
   *
   * @return array
   *   Keys are the original search term. Values are arrays of suggestions.
   */
  public function parseSpellCheckResult(QueryInterface $query, array $response): array {
    if (empty($response['suggest'])) {
      return [];
    }
    $result = [];
    $max_count = $query->getOption('search_api_spellcheck')['count'] ?? NULL;
    foreach ($this->flattenSuggestions($response['suggest']) as $key => $suggestions) {

      // Now the suggestions are in one list, we can sort them all.
      $suggestions = $this->sortSuggestions($suggestions);

      // Now suggestions are sorted, reduce them to just the text.
      $suggestionText = $this->getSuggestionText($suggestions);

      // Limit to the requested number of items.
      $result[$key] = array_values(array_slice($suggestionText, 0, $max_count));
    }
    return $result;
  }

  /**
   * Flattens suggestions from being indexed per-field to one array.
   *
   * Return value is indexed per search key. Values are array of suggestions.
   */
  private function flattenSuggestions(array $suggestions): array {
    $candidates = [];
    // Suggestions are built exposing their suggest per fulltext configured
    // field for the Open Search index.
    foreach ($suggestions as $field_suggestions) {
      foreach ($field_suggestions as $term_corrections) {
        foreach ($term_corrections['options'] as $option) {
          $candidates[$term_corrections['text']][] = $option;
        }
      }
    }
    return $candidates;
  }

  /**
   * Sorts an array of suggestions.
   *
   * Sort by score descending, then freq descending. We're aiming to find the
   * suggestion with the highest score, that appears the most frequently.
   */
  private function sortSuggestions(array $suggestions): array {
    usort($suggestions, function ($a, $b) {
      if ($b['score'] === $a['score']) {
        return $b['freq'] <=> $a['freq'];
      }
      return $b['score'] <=> $a['score'];
    });
    return $suggestions;
  }

  /**
   * Reduces an array of suggestions to just the text.
   *
   * This builds an array of the unique 'text' values from each suggestion.
   */
  private function getSuggestionText(array $suggestions): array {
    $terms = array_map(function ($i) {
      return $i['text'];
    }, $suggestions);

    // Dedupe the suggestions.
    return array_unique($terms);
  }

}
