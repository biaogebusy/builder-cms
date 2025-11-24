<?php

namespace Drupal\commerce_order\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\commerce_order\Adjustment;

/**
 * Plugin implementation of the 'commerce_adjustment' field type.
 *
 * @property mixed $value
 */
#[FieldType(
  id: "commerce_adjustment",
  label: new TranslatableMarkup("Adjustment"),
  description: new TranslatableMarkup("Stores adjustments to the parent entity's price."),
  category: "commerce",
  default_widget: "commerce_adjustment_default",
  default_formatter: "commerce_adjustment_default",
  no_ui: TRUE,
  list_class: AdjustmentItemList::class,
)]
class AdjustmentItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['value'] = DataDefinition::create('adjustment_item')
      ->setLabel(t('Value'))
      ->setRequired(TRUE);

    $properties['type'] = DataDefinition::create('adjustment_property')
      ->setLabel(t('Type'))
      ->setComputed(TRUE);

    $properties['label'] = DataDefinition::create('adjustment_property')
      ->setLabel(t('Label'))
      ->setComputed(TRUE);

    $properties['amount'] = DataDefinition::create('adjustment_property')
      ->setLabel(t('Amount'))
      ->setComputed(TRUE);

    $properties['source_id'] = DataDefinition::create('adjustment_property')
      ->setLabel(t('Source ID'))
      ->setComputed(TRUE);

    $properties['percentage'] = DataDefinition::create('adjustment_property')
      ->setLabel(t('Amount'))
      ->setComputed(TRUE);

    $properties['included'] = DataDefinition::create('adjustment_property')
      ->setLabel(t('Included'))
      ->setComputed(TRUE);

    $properties['locked'] = DataDefinition::create('adjustment_property')
      ->setLabel(t('Locked'))
      ->setComputed(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return $this->value === NULL || !$this->value instanceof Adjustment;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if (is_array($values)) {
      // The property definition causes the adjustment to be in 'value' key.
      $values = reset($values);
    }
    if (!$values instanceof Adjustment) {
      $values = NULL;
    }
    parent::setValue($values, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'value' => [
          'description' => 'The adjustment value.',
          'type' => 'blob',
          'not null' => TRUE,
          'serialize' => TRUE,
        ],
      ],
    ];
  }

}
