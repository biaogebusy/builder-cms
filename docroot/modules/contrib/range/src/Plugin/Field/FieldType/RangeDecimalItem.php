<?php

namespace Drupal\range\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'range_decimal' field type.
 *
 * @FieldType(
 *   id = "range_decimal",
 *   label = @Translation("Range (decimal)"),
 *   description = @Translation("This field stores a fixed decimal range in the database."),
 *   category = @Translation("Numeric range"),
 *   default_widget = "range",
 *   default_formatter = "range_decimal",
 *   constraints = {"RangeBothValuesRequired" = {}, "RangeFromGreaterTo" = {}}
 * )
 */
class RangeDecimalItem extends RangeItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'precision' => 10,
      'scale' => 2,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    return static::propertyDefinitionsByType('string');
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::fieldSettingsForm($form, $form_state);

    $element['min']['#step'] = $element['max']['#step'] = pow(0.1, $this->definition->getSetting('scale'));

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = [];

    $precision_range = range(10, 32);
    $scale_range = range(0, 10);
    $element['precision'] = [
      '#type' => 'select',
      '#title' => $this->t('Precision'),
      '#options' => array_combine($precision_range, $precision_range),
      '#default_value' => $this->getSetting('precision'),
      '#description' => $this->t('The total number of digits to store in the database, including those to the right of the decimal.'),
      '#disabled' => $has_data,
    ];
    $element['scale'] = [
      '#type' => 'select',
      '#title' => $this->t('Scale', [], ['decimal places']),
      '#options' => array_combine($scale_range, $scale_range),
      '#default_value' => $this->getSetting('scale'),
      '#description' => $this->t('The number of digits to the right of the decimal.'),
      '#disabled' => $has_data,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    $this->from = round($this->from, $this->getSetting('scale'));
    $this->to = round($this->to, $this->getSetting('scale'));
  }

  /**
   * {@inheritdoc}
   */
  public static function getColumnSpecification(FieldStorageDefinitionInterface $field_definition) {
    return [
      'type' => 'numeric',
      'precision' => $field_definition->getSetting('precision'),
      'scale' => $field_definition->getSetting('scale'),
    ];
  }

}
