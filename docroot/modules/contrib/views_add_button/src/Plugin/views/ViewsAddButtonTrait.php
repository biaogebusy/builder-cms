<?php

namespace Drupal\views_add_button\Plugin\views;

trait ViewsAddButtonTrait {

  /**
   * @return array
   */
  public function viewsAddButtonGetReplacementCharacters() {
    return [
      '%5B' => '[',
      '%5D' => ']',
      '%7B' => '{',
      '%7D' => '}',
      '&amp;' => '&'
    ];
  }

  /**
   * Perform bracket and special character replacement.
   *
   * For security reasons, we are not opening this to most characters.
   * @see https://www.drupal.org/project/views_add_button/issues/3095849
   *
   * @param string $str
   *   String to perform character replacement
   * @return string
   *   Transformed string
   */
  public function viewsAddButtonCleanupSpecialCharacters($str = '') {
    $replace = $this->viewsAddButtonGetReplacementCharacters();

    return strtr($str, $replace);
  }

  /**
   * @param null $values
   * @return array
   */
  public function getQueryString($values = NULL) {
    $query_string = $this->options['query_string'];
    $q = NULL;
    if (isset($value->index)) {
      $q = $this->options['tokenize'] ? $this->tokenizeValue($query_string, $values->index) : $query_string;
    }
    else {
      $q = $this->options['tokenize'] ? $this->tokenizeValue($query_string) : $query_string;
    }
    $query_opts = [];
    if ($q) {
      $q = $this->viewsAddButtonCleanupSpecialCharacters($q);
      $qparts = explode('&', $q);

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