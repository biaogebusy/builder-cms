<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\Plugin\DiffGenerator;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_share_diff\DiffGenerator\DiffGeneratorPluginBase;

/**
 * Plugin to diff core field types.
 *
 * @DiffGenerator(
 *   id = "core_field_diff_parser",
 *   label = @Translation("Core Field Parser"),
 *   field_types = {
 *     "decimal",
 *     "integer",
 *     "float",
 *     "email",
 *     "telephone",
 *     "date",
 *     "uri",
 *     "string",
 *     "timestamp",
 *     "created",
 *     "string_long",
 *     "language",
 *     "uuid",
 *     "map",
 *     "datetime",
 *     "boolean"
 *   },
 * )
 */
class CoreFieldDiffParser extends DiffGeneratorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items, array $remote_field_data = []) {
    $result = [];
    $definition = $field_items->getFieldDefinition();
    $type = $definition->getType();

    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        $values = $field_item->getValue();
        if (isset($values['value'])) {
          $result[$field_key] = $values['value'];
          if ($type == 'boolean') {
            $result[$field_key] = ($result[$field_key] == 1);
          }
          // For some reason local numbers are represented as strings,
          // while remote numbers are indeed numbers. In order to avoid fake
          // differences, simply cast all numbers to strings.
          elseif (in_array($type, ['float', 'integer', 'decimal', 'timestamp'])) {
            $result[$field_key] = (string) $result[$field_key];
          }
        }
      }
    }

    return $result;
  }

}
