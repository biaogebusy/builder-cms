<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\Plugin\DiffGenerator;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_share_diff\DiffGenerator\DiffGeneratorPluginBase;

/**
 * Plugin to diff text with summary fields.
 *
 * @DiffGenerator(
 *   id = "text_summary_field_diff_parser",
 *   label = @Translation("Text with Summary Field Parser"),
 *   field_types = {
 *     "text_with_summary"
 *   },
 * )
 */
class TextWithSummaryFieldDiffParser extends DiffGeneratorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items, array $remote_field_data = []) {
    $result = [];
    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items as $field_key => $field_item) {
      $values = $field_item->getValue();
      // Handle the text summary.
      if (isset($values['summary'])) {
        if ($values['summary'] != "") {
          $label = (string) $this->t('Summary');
          $result[$field_key][$label] = $values['summary'];
        }
      }

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
