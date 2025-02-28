<?php

namespace Drupal\views_add_button\Plugin\views;

trait ViewsAddButtonTrait {

  public function viewsAddButtonCleanupSpecialCharacters($str = '') {
    /*
       * Perform bracket and special character replacement.
       * For security reasons, we are not opening this to most characters.
       * @see https://www.drupal.org/project/views_add_button/issues/3095849
       */
    $replace = ['%5B' => '[', '%5D' => ']', '&amp;' => '&'];
    return strtr($str, $replace);
  }

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
          $query_opts[$p[0]] = $p[1];
        }
      }
    }
    return $query_opts;
  }

}