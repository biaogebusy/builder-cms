<?php

declare(strict_types=1);

namespace Drupal\elasticsearch_connector\Plugin\search_api\data_type;

use Drupal\search_api\Plugin\search_api\data_type\TextDataType;

/**
 * Defines a class for a search-as-you-type data type.
 *
 * @SearchApiDataType(
 *   id = "elasticsearch_connector_search_as_you_type",
 *   label = @Translation("Search-As-You-Type"),
 *   description = @Translation("search-as-you-type"),
 *   fallback_type = "text",
 * )
 */
final class SearchAsYouTypeDataType extends TextDataType {

}
