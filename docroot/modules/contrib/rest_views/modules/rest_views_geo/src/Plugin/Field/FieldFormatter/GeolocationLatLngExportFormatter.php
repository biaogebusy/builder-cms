<?php

namespace Drupal\rest_views_geo\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\rest_views\SerializedData;

/**
 * Plugin implementation of the 'geolocation_latlng' export formatter.
 *
 * @FieldFormatter(
 *   id = "geolocation_latlng_formatter_export",
 *   module = "module_name",
 *   label = @Translation("Export"),
 *   field_types = {
 *     "geolocation"
 *   }
 * )
 */
class GeolocationLatLngExportFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      $data = [
        'lat' => $item->lat,
        'lng' => $item->lng,
      ];
      $elements[$delta] = [
        '#type' => 'data',
        '#data' => SerializedData::create($data),
      ];
    }

    return $elements;
  }

}
