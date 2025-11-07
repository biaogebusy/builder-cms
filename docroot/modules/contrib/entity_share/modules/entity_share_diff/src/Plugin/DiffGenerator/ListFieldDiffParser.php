<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\Plugin\DiffGenerator;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_share_diff\DiffGenerator\DiffGeneratorPluginBase;

/**
 * Plugin to diff list fields.
 *
 * @DiffGenerator(
 *   id = "list_field_diff_parser",
 *   label = @Translation("List Field Diff"),
 *   field_types = {
 *     "list_string",
 *     "list_integer",
 *     "list_float"
 *   },
 * )
 */
class ListFieldDiffParser extends DiffGeneratorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items, array $remote_field_data = []) {
    $result = [];

    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items as $field_key => $field_item) {
      // Build the array for comparison only if the field is not empty.
      if (!$field_item->isEmpty()) {
        $possible_options = $field_item->getPossibleOptions();
        $values = $field_item->getValue();
        if ($possible_options) {
          $result[$field_key] = $possible_options[$values['value']] . ' (' . $values['value'] . ')';
        }
        else {
          $result[$field_key] = $possible_options[$values['value']];
        }
      }
    }

    return $result;
  }

}
