<?php

namespace Drupal\elasticsearch_connector\Plugin\search_api\data_type;

use Drupal\search_api\Plugin\search_api\data_type\DateDataType;

/**
 * Provides a full date data type.
 *
 * @SearchApiDataType(
 *   id = "elasticsearch_connector_full_date",
 *   label = @Translation("Full Date"),
 *   description = @Translation("Full Date"),
 *   fallback_type = "date"
 * )
 */
class FullDateDataType extends DateDataType {}
