<?php

namespace Drupal\Tests\range\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Base class for the range module's tests.
 */
abstract class RangeBrowserTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Entity type ID to test against.
   *
   * @var string
   */
  protected $entityTypeId = 'node';

  /**
   * Bundle to test against.
   *
   * @var string
   */
  protected $bundle = 'test_bundle';

  /**
   * A user that can edit content types.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Field entity.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected $field;

  /**
   * Field storage configuration entity.
   *
   * @var \Drupal\field\Entity\FieldStorageConfig
   */
  protected $fieldStorage;

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
  protected function getTestFieldNameRaw($field_type = 'range_integer') {
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
  protected function getTestFieldName($field_type = 'range_integer') {
    return 'field_' . $this->getTestFieldNameRaw($field_type);
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
  protected function getTestFieldStorageSettings($field_type = 'range_integer') {
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

    return [];
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
  protected function getTestFieldSettings($field_type = 'range_integer') {
    switch ($field_type) {
      case 'range_integer':
      case 'range_float':
      case 'range_decimal':
        return [
          'min' => 5,
          'max' => 25,
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

    return [];
  }

  /**
   * Creates a range field of a given type.
   *
   * @param string $field_type
   *   Range field type. Could be one of the following values: range_integer,
   *   range_float or range_decimal.
   */
  protected function createRangeField($field_type = 'range_integer') {
    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => $this->getTestFieldName($field_type),
      'entity_type' => $this->entityTypeId,
      'type' => $field_type,
      'settings' => $this->getTestFieldStorageSettings($field_type),
    ]);
    $this->fieldStorage->save();
    $this->field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => $this->bundle,
      'settings' => $this->getTestFieldSettings($field_type),
    ]);
    $this->field->save();

    \Drupal::service('entity_display.repository')
      ->getFormDisplay($this->entityTypeId, $this->bundle, 'default')
      ->setComponent($this->getTestFieldName($field_type), [
        'type' => 'range',
      ])
      ->save();
  }

  /**
   * Deletes previously created range field.
   */
  public function deleteRangeField() {
    $this->field->delete();
    $this->fieldStorage->delete();
  }

  /**
   * {@inheritdoc}
   */
  protected function createContentType(array $values = []) {
    $values['type'] = $this->bundle;
    return parent::createContentType($values);
  }

}
