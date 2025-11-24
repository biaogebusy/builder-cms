<?php

namespace Drupal\shs\Plugin\views\filter;

use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;

/**
 * Extends search_api's extension of taxonomy_index_tid views filter.
 */
class SearchApiShsTaxonomyIndexTid extends ShsTaxonomyIndexTid {
  use SearchApiFilterTrait;

}
