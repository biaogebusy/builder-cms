<?php

namespace Drupal\views_add_button\Plugin\views;

/**
 * Views Add Button trait.
 */
trait ViewsAddButtonTrait {

  /**
   * Get Replacement Characters.
   *
   * @return array
   *   Replacement characters.
   */
  public function viewsAddButtonGetReplacementCharacters() {
    return [
      '%5B' => '[',
      '%5D' => ']',
      '%7B' => '{',
      '%7D' => '}',
      '&amp;' => '&',
    ];
  }

  /**
   * Cleans up special characters in a string.
   *
   * @param string $str
   *   String to perform character replacement.
   *
   * @return string
   *   Transformed string
   */
  public function viewsAddButtonCleanupSpecialCharacters($str = '') {
    $replace = $this->viewsAddButtonGetReplacementCharacters();

    return strtr($str, $replace);
  }

  /**
   * Parses the query string option and returns an associative array.
   *
   * @param null|object $values
   *   Optional values object containing index property for token replacement.
   *
   * @return array
   *   An associative array of query options, where keys are parameters and
   *   values are their values.
   */
  public function getQueryString($values = NULL) {
    $query_string = $this->options['query_string'];
    if (isset($values->index)) {
      $q = $this->options['tokenize'] ? $this->tokenizeValue($query_string, $values->index) : $query_string;
    }
    else {
      $q = $this->options['tokenize'] ? $this->tokenizeValue($query_string) : $query_string;
    }
    $query_opts = [];
    if ($q) {
      $q = $this->viewsAddButtonCleanupSpecialCharacters($q);
      $qparts = explode('&', (string) $q);

      foreach ($qparts as $part) {
        $p = explode('=', $part);
        if (is_array($p) && count($p) > 1) {
          $query_opts[$p[0]] = trim($p[1]);
        }
      }
    }
    return $query_opts;
  }

}
