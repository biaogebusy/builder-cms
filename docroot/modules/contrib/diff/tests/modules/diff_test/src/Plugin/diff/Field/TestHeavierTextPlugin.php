<?php

declare(strict_types=1);

namespace Drupal\diff_test\Plugin\diff\Field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\diff\Attribute\FieldDiffBuilder;
use Drupal\diff\FieldDiffBuilderBase;

/**
 * Test diff builder with heavy weight.
 */
#[FieldDiffBuilder(
  id: 'test_heavier_text_plugin',
  label: new TranslatableMarkup('Test Heavier Text Plugin'),
  field_types: ['text'],
  weight: -10,
)]
class TestHeavierTextPlugin extends FieldDiffBuilderBase {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items): array {
    $result = [];

    // Every item from $field_items is of type FieldItemInterface.
    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        $values = $field_item->getValue();
        if (isset($values['value'])) {
          $result[$field_key][] = \str_replace('applicable', 'heavier_test_plugin', (string) $values['value']);
        }
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldStorageDefinitionInterface $field_definition): bool {
    return ($field_definition->getName() == 'test_field' || $field_definition->getName() == 'test_field_lighter');
  }

}
