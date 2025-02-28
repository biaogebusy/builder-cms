<?php

namespace Drupal\elasticsearch_connector\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a full date data type.
 *
 * @SearchApiDataType(
 *   id = "full_date",
 *   label = @Translation("Full Date"),
 *   description = @Translation("Formatted date. Use for pre-epoch dates (dates before 1st of January 1970)"),
 *   default = "true"
 * )
 */
class FullDateDataType extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    if ((string) $value === '') {
      return NULL;
    }
    if (is_numeric($value)) {
      // It could come from a created timestamp.
      return date(DATE_ATOM, $value);
    }
    // It could come from a date field.
    return $value;

  }

}
