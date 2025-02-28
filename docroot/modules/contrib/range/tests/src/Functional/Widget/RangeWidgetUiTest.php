<?php

namespace Drupal\Tests\range\Functional\Widget;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\range\Traits\RangeTestTrait;

/**
 * Tests the text fields field widget UI.
 *
 * @group range
 */
class RangeWidgetUiTest extends BrowserTestBase {

  use RangeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Field type to test against.
   *
   * @var string
   */
  protected $fieldType;

  /**
   * Field name to test against.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
    'entity_test',
    'range',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser([
      'administer entity_test content',
      'administer entity_test form display',
    ]));
  }

  /**
   * Tests widget UI.
   */
  public function testTextFieldsWidgetUi() {
    foreach ($this->getRangeFieldTypes() as $this->fieldType) {
      // Create field and set form display.
      $this->fieldName = $this->getFieldName($this->fieldType);
      $this->createField($this->fieldType);
      // Assert that widget UI works as expected.
      $this->assertTextFieldsWidgetUi();
      // Delete field.
      $this->deleteField();
    }
  }

  /**
   * Asserts that widget settings UI works as expected.
   */
  protected function assertTextFieldsWidgetUi() {
    $this->drupalGet('entity_test/structure/entity_test/form-display');

    // Enable Default formatter.
    $edit = [
      "fields[$this->fieldName][parent]" => 'content',
      "fields[$this->fieldName][region]" => 'content',
      "fields[$this->fieldName][type]" => 'range',
    ];
    $this->submitForm($edit, 'Save');

    // Ensure that summary is correct.
    $this->assertSession()->pageTextContains('FROM form element label: From');
    $this->assertSession()->pageTextContains('TO form element label: to');
    $this->assertSession()->pageTextContains('No FROM placeholder');
    $this->assertSession()->pageTextContains('No TO placeholder');

    // Go to widget settings form.
    $this->submitForm([], $this->fieldName . '_settings_edit');

    // Ensure that form displays correct set of fields.
    $this->assertSession()->fieldValueEquals('FROM form element label', 'From');
    $this->assertSession()->fieldValueEquals('TO form element label', 'to');
    $this->assertSession()->fieldValueEquals('FROM placeholder', '');
    $this->assertSession()->fieldValueEquals('TO placeholder', '');

    // Update widget settings.
    $widget_settings = $this->getWidgetSettings();
    $edit = [
      "fields[$this->fieldName][settings_edit_form][settings][label][from]" => $widget_settings['label']['from'],
      "fields[$this->fieldName][settings_edit_form][settings][label][to]" => $widget_settings['label']['to'],
      "fields[$this->fieldName][settings_edit_form][settings][placeholder][from]" => $widget_settings['placeholder']['from'],
      "fields[$this->fieldName][settings_edit_form][settings][placeholder][to]" => $widget_settings['placeholder']['to'],
    ];
    $this->submitForm($edit, 'Update');

    // Ensure that summary is correct.
    $this->assertSession()->pageTextContains('FROM form element label: ' . $widget_settings['label']['from']);
    $this->assertSession()->pageTextContains('TO form element label: ' . $widget_settings['label']['to']);
    $this->assertSession()->pageTextContains('FROM placeholder: ' . $widget_settings['placeholder']['from']);
    $this->assertSession()->pageTextContains('TO placeholder: ' . $widget_settings['placeholder']['to']);

    // Go to widget settings form.
    $this->submitForm([], $this->fieldName . '_settings_edit');

    // Ensure that form displays correct set of fields.
    $this->assertSession()->fieldValueEquals('FROM form element label', $widget_settings['label']['from']);
    $this->assertSession()->fieldValueEquals('TO form element label', $widget_settings['label']['to']);
    $this->assertSession()->fieldValueEquals('FROM placeholder', $widget_settings['placeholder']['from']);
    $this->assertSession()->fieldValueEquals('TO placeholder', $widget_settings['placeholder']['to']);
  }

}
