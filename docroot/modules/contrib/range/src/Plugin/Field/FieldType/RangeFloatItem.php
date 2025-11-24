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
 *   description = {
 *     @Translation("In most instances, it is best to use Range (decimal) instead, as decimal numbers stored as floats may contain errors in precision"),
 *     @Translation("This type of field offers faster processing and more compact storage, but the differences are typically negligible on modern sites"),
 *     @Translation("For example, 123.4-128.9 (should be used in imprecise contexts such as a walking trail distance)"),
 *   },
 *   category = "range",
 *   weight = -10,
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
