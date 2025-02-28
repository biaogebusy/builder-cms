<?php

namespace Drupal\Tests\range\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;

/**
 * Tests adding range fields and their settings via field UI.
 *
 * @group range
 */
class RangeFieldUiTest extends RangeBrowserTestBase {

  use FieldUiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'range', 'field_ui', 'block'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer content types',
      'administer node fields',
      'administer node display',
    ]);
    $this->drupalLogin($this->adminUser);
    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->createContentType();
  }

  /**
   * Test creating range field via field UI.
   *
   * @param string $field_type
   *   Range field type. Could be one of the following values: range_integer,
   *   range_float or range_decimal.
   */
  protected function rangeTestAddNewField($field_type = 'range_integer') {
    $bundle_path = "admin/structure/types/manage/{$this->bundle}";
    $field_name = $this->getTestFieldNameRaw($field_type);
    $label = $this->randomMachineName();
    $storage_edit = $this->getStorageEdit($field_type);
    $field_edit = $this->getFieldEdit($field_type);

    $this->fieldUIAddNewField($bundle_path, $field_name, $label, $field_type, $storage_edit, $field_edit);

    // Clears all caches related to field definitions.
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Test range field storage settings.
   *
   * @param string $field_type
   *   Range field type. Could be one of the following values: range_integer,
   *   range_float or range_decimal.
   */
  protected function rangeTestFieldStorageSettings($field_type = 'range_integer') {
    $field_name = $this->getTestFieldName($field_type);
    $settings = FieldStorageConfig::loadByName($this->entityTypeId, $field_name)->getSettings();
    $test_settings = $this->getTestFieldStorageSettings($field_type);

    switch ($field_type) {
      case 'range_decimal':
        $this->assertEquals(
          $settings['precision'], $test_settings['precision'],
          new FormattableMarkup(
            'Correct precision setting %value found for the %field_type field type',
            [
              '%value' => $settings['precision'],
              '%field_type' => $field_type,
            ]
          )
        );
        $this->assertEquals(
          $settings['scale'], $test_settings['scale'],
          new FormattableMarkup(
            'Correct scale setting %value found for the %field_type field type',
            [
              '%value' => $settings['scale'],
              '%field_type' => $field_type,
            ]
          )
        );
        break;
    }
  }

  /**
   * Test range field settings.
   *
   * @param string $field_type
   *   Range field type. Could be one of the following values: range_integer,
   *   range_float or range_decimal.
   */
  protected function rangeTestFieldSettings($field_type = 'range_integer') {
    $field_name = $this->getTestFieldName($field_type);
    $settings = FieldConfig::loadByName($this->entityTypeId, $this->bundle, $field_name)->getSettings();
    $test_settings = $this->getTestFieldSettings($field_type);

    switch ($field_type) {
      case 'range_integer':
      case 'range_float':
      case 'range_decimal':
        $this->assertEquals(
          $settings['min'], $test_settings['min'],
          new FormattableMarkup(
            'Correct minimum setting %value found for the %field_type field type',
            [
              '%value' => $settings['min'],
              '%field_type' => $field_type,
            ]
          )
        );
        $this->assertEquals($settings['max'], $test_settings['max'],
          new FormattableMarkup(
            'Correct maximum setting %value found for the %field_type field type',
            [
              '%value' => $settings['max'],
              '%field_type' => $field_type,
            ]
          )
        );
        $this->assertEquals(
          $settings['field']['prefix'], $test_settings['field']['prefix'],
          new FormattableMarkup(
            'Correct FIELD prefix setting %value found for the %field_type field type',
            [
              '%value' => $settings['field']['prefix'],
              '%field_type' => $field_type,
            ]
          )
        );
        $this->assertEquals(
          $settings['field']['suffix'], $test_settings['field']['suffix'],
          new FormattableMarkup(
            'Correct FIELD suffix setting %value found for the %field_type field type',
            [
              '%value' => $settings['field']['suffix'],
              '%field_type' => $field_type,
            ]
          )
        );
        $this->assertEquals(
          $settings['from']['prefix'], $test_settings['from']['prefix'],
          new FormattableMarkup(
            'Correct FROM prefix setting %value found for the %field_type field type',
            [
              '%value' => $settings['from']['prefix'],
              '%field_type' => $field_type,
            ]
          )
        );
        $this->assertEquals(
          $settings['from']['suffix'], $test_settings['from']['suffix'],
          new FormattableMarkup(
            'Correct FROM suffix setting %value found for the %field_type field type',
            [
              '%value' => $settings['from']['suffix'],
              '%field_type' => $field_type,
            ]
          )
        );
        $this->assertEquals(
          $settings['to']['prefix'], $test_settings['to']['prefix'],
          new FormattableMarkup(
            'Correct TO prefix setting %value found for the %field_type field type',
            [
              '%value' => $settings['to']['prefix'],
              '%field_type' => $field_type,
            ]
          )
        );
        $this->assertEquals(
          $settings['to']['suffix'], $test_settings['to']['suffix'],
          new FormattableMarkup(
            'Correct TO suffix setting %value found for the %field_type field type',
            [
              '%value' => $settings['to']['suffix'],
              '%field_type' => $field_type,
            ]
          )
        );
        $this->assertEquals(
          $settings['combined']['prefix'], $test_settings['combined']['prefix'],
          new FormattableMarkup(
            'Correct COMBINED prefix setting %value found for the %field_type field type',
            [
              '%value' => $settings['combined']['prefix'],
              '%field_type' => $field_type,
            ]
          )
        );
        $this->assertEquals(
          $settings['combined']['suffix'], $test_settings['combined']['suffix'],
          new FormattableMarkup(
            'Correct COMBINED suffix setting %value found for the %field_type field type',
            [
              '%value' => $settings['combined']['suffix'],
              '%field_type' => $field_type,
            ]
          )
        );
        break;
    }
  }

  /**
   * Returns storage edit array for a given field type.
   *
   * @param string $field_type
   *   Range field type. Could be one of the following values: range_integer,
   *   range_float or range_decimal.
   *
   * @return array
   *   Range field storage edit array.
   */
  protected function getStorageEdit($field_type = 'range_integer') {
    $test_settings = $this->getTestFieldStorageSettings($field_type);
    switch ($field_type) {
      case 'range_integer':
      case 'range_float':
        return [];

      case 'range_decimal':
        return [
          'settings[precision]' => $test_settings['precision'],
          'settings[scale]' => $test_settings['scale'],
        ];
    }
  }

  /**
   * Returns field edit array for a given field type.
   *
   * @param string $field_type
   *   Range field type. Could be one of the following values: range_integer,
   *   range_float or range_decimal.
   *
   * @return array
   *   Range field edit array.
   */
  protected function getFieldEdit($field_type = 'range_integer') {
    $test_settings = $this->getTestFieldSettings($field_type);
    switch ($field_type) {
      case 'range_integer':
      case 'range_float':
      case 'range_decimal':
        return [
          'settings[min]' => $test_settings['min'],
          'settings[max]' => $test_settings['max'],
          'settings[field][prefix]' => $test_settings['field']['prefix'],
          'settings[field][suffix]' => $test_settings['field']['suffix'],
          'settings[from][prefix]' => $test_settings['from']['prefix'],
          'settings[from][suffix]' => $test_settings['from']['suffix'],
          'settings[to][prefix]' => $test_settings['to']['prefix'],
          'settings[to][suffix]' => $test_settings['to']['suffix'],
          'settings[combined][prefix]' => $test_settings['combined']['prefix'],
          'settings[combined][suffix]' => $test_settings['combined']['suffix'],
        ];
    }
  }

  /**
   * Tests adding range fields and their settings via field UI.
   */
  public function testRangeFieldsUi() {
    foreach (['range_integer', 'range_float', 'range_decimal'] as $field_type) {
      $this->rangeTestAddNewField($field_type);
      $this->rangeTestFieldStorageSettings($field_type);
      $this->rangeTestFieldSettings($field_type);
    }
  }

}
