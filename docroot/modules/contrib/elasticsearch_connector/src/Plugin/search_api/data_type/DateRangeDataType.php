<?php

namespace Drupal\elasticsearch_connector\Plugin\search_api\data_type;

use Drupal\search_api\Plugin\search_api\data_type\DateDataType;

/**
 * Provides a date range data type.
 *
 * @SearchApiDataType(
 *   id = "elasticsearch_connector_date_range",
 *   label = @Translation("Date range"),
 *   description = @Translation("Date field that contains date ranges."),
 *   fallback = "date",
 *   prefix = "dr"
 * )
 */
class DateRangeDataType extends DateDataType {

}
