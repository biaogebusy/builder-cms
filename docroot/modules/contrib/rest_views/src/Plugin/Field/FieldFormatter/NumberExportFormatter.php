<?php

namespace Drupal\rest_views\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\NumericUnformattedFormatter;
use Drupal\rest_views\SerializedData;

/**
 * Export a number as a serialized value.
 *
 * @FieldFormatter(
 *   id = "number_export",
 *   label = @Translation("Export number"),
 *   field_types = {
 *     "integer",
 *     "decimal",
 *     "float"
 *   }
 * )
 */
class NumberExportFormatter extends NumericUnformattedFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);

    foreach ($elements as $delta => $element) {
      $elements[$delta] = [
        '#type' => 'data',
        '#data' => SerializedData::create(0 + $element['#markup']),
      ];
    }

    return $elements;
  }

}
