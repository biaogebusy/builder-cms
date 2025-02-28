<?php

namespace Drupal\commerce\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'commerce_remote_id_default' formatter.
 *
 * @FieldFormatter(
 *   id = "commerce_remote_id_default",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "commerce_remote_id"
 *   }
 * )
 */
class RemoteIdDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      // Render each element as markup.
      $element[$delta] = ['#markup' => $item->provider . ':' . $item->remote_id];
    }

    return $element;
  }

}
