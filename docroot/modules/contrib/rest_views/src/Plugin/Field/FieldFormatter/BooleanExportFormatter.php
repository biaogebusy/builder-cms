<?php

namespace Drupal\rest_views\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\rest_views\SerializedData;

/**
 * Export a boolean as a serialized value.
 *
 * @FieldFormatter(
 *   id = "boolean_export",
 *   label = @Translation("Export boolean"),
 *   field_types = {
 *     "boolean",
 *   }
 * )
 */
class BooleanExportFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'data',
        '#data' => SerializedData::create((bool) $item->value),
      ];
    }

    return $elements;
  }

}
