<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\Plugin\DiffGenerator;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_share_diff\DiffGenerator\DiffGeneratorPluginBase;

/**
 * Plugin to diff text fields.
 *
 * @DiffGenerator(
 *   id = "text_field_diff_parser",
 *   label = @Translation("Text Field Diff Parser"),
 *   field_types = {
 *     "text",
 *     "text_long"
 *   },
 * )
 */
class TextFieldDiffParser extends DiffGeneratorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items, array $remote_field_data = []) {
    $result = [];
    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items as $field_key => $field_item) {
      $values = $field_item->getValue();
      // Compare field values.
      if (isset($values['value'])) {
        // Check if summary or text format are included in the diff.
        $label = (string) $this->t('Value');
        $result[$field_key][$label] = $values['value'];
      }
    }

    return $result;
  }

}
