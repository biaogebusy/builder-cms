<?php

namespace Drupal\range\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'range_float' field type.
 *
 * @FieldType(
 *   id = "range_float",
 *   label = @Translation("Range (float)"),
 *   description = @Translation("This field stores a float range in the database."),
 *   category = @Translation("Numeric range"),
 *   default_widget = "range",
 *   default_formatter = "range_decimal",
 *   constraints = {"RangeBothValuesRequired" = {}, "RangeFromGreaterTo" = {}}
 * )
 */
class RangeFloatItem extends RangeItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    return static::propertyDefinitionsByType('float');
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::fieldSettingsForm($form, $form_state);

    $element['min']['#step'] = $element['max']['#step'] = 'any';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function getColumnSpecification(FieldStorageDefinitionInterface $field_definition) {
    return [
      'type' => 'float',
    ];
  }

}
