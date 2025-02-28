<?php

namespace Drupal\Tests\range\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Provides helper methods to test range fields.
 */
trait RangeTestTrait {

  /**
   * Entity type ID to test against.
   *
   * @var string
   */
  protected $entityType = 'entity_test';

  /**
   * Bundle to test against.
   *
   * @var string
   */
  protected $bundle = 'entity_test';

  /**
   * Field storage used for tests.
   *
   * @var \Drupal\Core\Field\FieldStorageDefinitionInterface
   */
  protected $fieldStorage;

  /**
   * Field used for tests.
   *
   * @var \Drupal\Core\Field\FieldConfigInterface
   */
  protected $field;

  /**
   * Form display to test.
   *
   * @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   */
  protected $formDisplay;

  /**
   * View display to test.
   *
   * @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  protected $viewDisplay;

  /**
   * Returns raw field name (without "field_" prefix added by Drupal field UI).
   *
   * @param string $field_type
   *   Range field type. Could be one of the following values: range_integer,
   *   range_float or range_decimal.
   *
   * @return string
   *   Raw field name.
   */
  protected function getFieldNameRaw($field_type = 'range_integer') {
    return "$field_type";
  }

  /**
   * Returns field name (with "field_" prefix added by Drupal field UI).
   *
   * @param string $field_type
   *   Range field type. Could be one of the following values: range_integer,
   *   range_float or range_decimal.
   *
   * @return string
   *   Field name.
   */
  protected function getFieldName($field_type = 'range_integer') {
    return 'field_' . $this->getFieldNameRaw($field_type);
  }

  /**
   * Returns test range field storage settings.
   *
   * @param string $field_type
   *   Range field type. Could be one of the following values: range_integer,
   *   range_float or range_decimal.
   *
   * @return array
   *   Range field storage settings.
   */
  protected function getFieldStorageSettings($field_type = 'range_integer') {
    switch ($field_type) {
      case 'range_integer':
      case 'range_float':
        return [];

      case 'range_decimal':
        return [
          'precision' => 12,
          'scale' => 4,
        ];
    }
  }

  /**
   * Returns test range field settings.
   *
   * @param string $field_type
   *   Range field type. Could be one of the following values: range_integer,
   *   range_float or range_decimal.
   *
   * @return array
   *   Range field settings.
   */
  protected function getFieldSettings($field_type = 'range_integer') {
    switch ($field_type) {
      case 'range_integer':
      case 'range_float':
      case 'range_decimal':
        return [
          'min' => 0,
          'max' => 100000,
          'field' => [
            'prefix' => 'field_prefix',
            'suffix' => 'field_suffix',
          ],
          'from' => [
            'prefix' => 'from_prefix',
            'suffix' => 'from_suffix',
          ],
          'to' => [
            'prefix' => 'to_prefix',
            'suffix' => 'to_suffix',
          ],
          'combined' => [
            'prefix' => 'combined_prefix',
            'suffix' => 'combined_suffix',
          ],
        ];
    }
  }

  /**
   * Returns test range widget settings.
   *
   * @return array
   *   Range widget settings.
   */
  protected function getWidgetSettings() {
    return [
      'label' => [
        'from' => 'from_label',
        'to' => 'to_label',
      ],
      'placeholder' => [
        'from' => 'from_placeholder',
        'to' => 'to_placeholder',
      ],
    ];
  }

  /**
   * Creates a range field of a given type.
   *
   * @param string $field_type
   *   Range field type. Could be one of the following values: range_integer,
   *   range_float or range_decimal.
   */
  protected function createField($field_type = 'range_integer') {
    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => $this->getFieldName($field_type),
      'entity_type' => $this->entityType,
      'type' => $field_type,
      'settings' => $this->getFieldStorageSettings($field_type),
    ]);
    $this->fieldStorage->save();

    $this->field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => $this->bundle,
      'settings' => $this->getFieldSettings($field_type),
    ]);
    $this->field->save();
  }

  /**
   * Creates default form display.
   */
  protected function createFormDisplay() {
    if (!$this->formDisplay) {
      $values = [
        'targetEntityType' => $this->entityType,
        'bundle' => $this->bundle,
        'mode' => 'default',
        'status' => TRUE,
      ];
      $this->formDisplay = \Drupal::entityTypeManager()
        ->getStorage('entity_form_display')
        ->create($values);
    }
  }

  /**
   * Sets the display settings for a default form display.
   *
   * @param string $field_type
   *   Range field type. Could be one of the following values: range_integer,
   *   range_float or range_decimal.
   * @param array $settings
   *   Array of display settings.
   */
  protected function setFormDisplayComponent($field_type = 'range_integer', array $settings = []) {
    if (!$this->formDisplay) {
      $this->createFormDisplay();
    }
    $this->formDisplay->setComponent($this->getFieldName($field_type), [
      'type' => 'range',
      'settings' => $settings,
    ]);
    $this->formDisplay->save();
  }

  /**
   * Creates default view display.
   */
  protected function createViewDisplay() {
    if (!$this->viewDisplay) {
      $values = [
        'targetEntityType' => $this->entityType,
        'bundle' => $this->bundle,
        'mode' => 'default',
        'status' => TRUE,
      ];
      $this->viewDisplay = \Drupal::entityTypeManager()
        ->getStorage('entity_view_display')
        ->create($values);
    }
  }

  /**
   * Sets the display settings for a default view display.
   *
   * @param string $field_type
   *   Range field type. Could be one of the following values: range_integer,
   *   range_float or range_decimal.
   * @param string $display_type
   *   Range field display type. Could be one of the following values:
   *   range_integer, range_decimal or range_unformatted.
   * @param array $settings
   *   Array of display settings.
   */
  protected function setViewDisplayComponent($field_type = 'range_integer', $display_type = 'range_integer', array $settings = []) {
    if (!$this->viewDisplay) {
      $this->createViewDisplay();
    }
    $this->viewDisplay->setComponent($this->getFieldName($field_type), [
      'type' => $display_type,
      'settings' => $settings,
    ]);
    $this->viewDisplay->save();
  }

  /**
   * Deletes previously created range field.
   */
  protected function deleteField() {
    $this->field->delete();
    $this->fieldStorage->delete();
  }

  /**
   * Returns list of available range field types.
   *
   * @return string[]
   *   Array of range field types.
   */
  protected function getRangeFieldTypes() {
    return [
      'range_integer',
      'range_decimal',
      'range_float',
    ];
  }

}
