<?php

namespace Drupal\metatag\Plugin\diff\Field;

use Drupal\diff\FieldDiffBuilderBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin to diff metatag fields.
 *
 * @FieldDiffBuilder(
 *   id = "metatag_field_diff_builder",
 *   label = @Translation("Metatag Field Diff"),
 *   field_types = {
 *     "metatag"
 *   },
 * )
 */
class MetatagFieldBuilder extends FieldDiffBuilderBase {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items) {
    $result = [];

    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        $values = $field_item->getValue();
        if (isset($values['value'])) {
          // Metatag data store as serialize string.
          $metatag_data = unserialize($values['value']);

          foreach ($metatag_data as $key => $value) {
            $result[$field_key][] = (string) $value;
          }
        }
      }
    }

    return $result;
  }

}
