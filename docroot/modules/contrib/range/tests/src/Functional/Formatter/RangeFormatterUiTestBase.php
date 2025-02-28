<?php

namespace Drupal\Tests\range\Functional\Formatter;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\range\Traits\RangeTestTrait;

/**
 * Base class for testing formatter UI.
 */
abstract class RangeFormatterUiTestBase extends BrowserTestBase {

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
   * Formatter type to test against.
   *
   * @var string
   */
  protected $formatterType;

  /**
   * WebAssert object.
   *
   * @var \Drupal\Tests\WebAssert
   */
  protected $session;

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
      'administer entity_test display',
    ]));

    $this->fieldName = $this->getFieldName($this->fieldType);
    $this->createField($this->fieldType);
    $this->session = $this->assertSession();
  }

  /**
   * Tests the formatter UI.
   */
  public function testFormatterUi() {
    $this->drupalGet('entity_test/structure/entity_test/display');

    // Enable Default formatter.
    $edit = [
      "fields[$this->fieldName][parent]" => 'content',
      "fields[$this->fieldName][region]" => 'content',
      "fields[$this->fieldName][type]" => $this->formatterType,
    ];
    $this->submitForm($edit, 'Save');

    // Ensure that default summary is correct.
    $this->session->pageTextContains('Equivalent values will be combined into a single value.');
    $this->session->pageTextNotContains('Display with FIELD value prefix and suffix');
    $this->session->pageTextNotContains('Display with FROM value prefix and suffix.');
    $this->session->pageTextNotContains('Display with TO value prefix and suffix.');
    $this->session->pageTextNotContains('Display with COMBINED value prefix and suffix.');

    // Go to formatter settings form.
    $this->submitForm([], $this->fieldName . '_settings_edit');

    $this->session->checkboxChecked('Combine equivalent values');
    $this->session->checkboxNotChecked('Display FIELD value prefix and suffix');
    $this->session->checkboxNotChecked('Display FROM value prefix and suffix');
    $this->session->checkboxNotChecked('Display TO value prefix and suffix');
    $this->session->checkboxNotChecked('Display COMBINED value prefix and suffix');

    // Update formatter settings.
    $edit = [
      "fields[$this->fieldName][settings_edit_form][settings][range_combine]" => FALSE,
      "fields[$this->fieldName][settings_edit_form][settings][field_prefix_suffix]" => TRUE,
      "fields[$this->fieldName][settings_edit_form][settings][from_prefix_suffix]" => TRUE,
      "fields[$this->fieldName][settings_edit_form][settings][to_prefix_suffix]" => FALSE,
      "fields[$this->fieldName][settings_edit_form][settings][combined_prefix_suffix]" => FALSE,
    ];
    $this->submitForm($edit, 'Update');

    // Ensure that summary is correct.
    $this->session->pageTextNotContains('Equivalent values will be combined into a single value.');
    $this->session->pageTextContains('Display with FIELD value prefix and suffix');
    $this->session->pageTextContains('Display with FROM value prefix and suffix.');
    $this->session->pageTextNotContains('Display with TO value prefix and suffix.');
    $this->session->pageTextNotContains('Display with COMBINED value prefix and suffix.');

    // Go to formatter settings form.
    $this->submitForm([], $this->fieldName . '_settings_edit');

    // Ensure that form displays correct set of fields.
    $this->session->checkboxNotChecked('Combine equivalent values');
    $this->session->checkboxChecked('Display FIELD value prefix and suffix');
    $this->session->checkboxChecked('Display FROM value prefix and suffix');
    $this->session->checkboxNotChecked('Display TO value prefix and suffix');
    $this->session->checkboxNotChecked('Display COMBINED value prefix and suffix');

    // Update formatter settings.
    $edit = [
      "fields[$this->fieldName][settings_edit_form][settings][range_combine]" => TRUE,
      "fields[$this->fieldName][settings_edit_form][settings][field_prefix_suffix]" => FALSE,
      "fields[$this->fieldName][settings_edit_form][settings][from_prefix_suffix]" => FALSE,
      "fields[$this->fieldName][settings_edit_form][settings][to_prefix_suffix]" => TRUE,
      "fields[$this->fieldName][settings_edit_form][settings][combined_prefix_suffix]" => TRUE,
    ];
    $this->submitForm($edit, 'Update');

    // Ensure that summary is correct.
    $this->session->pageTextContains('Equivalent values will be combined into a single value.');
    $this->session->pageTextNotContains('Display with FIELD value prefix and suffix.');
    $this->session->pageTextNotContains('Display with FROM value prefix and suffix.');
    $this->session->pageTextContains('Display with TO value prefix and suffix.');
    $this->session->pageTextContains('Display with COMBINED value prefix and suffix.');

    // Go to formatter settings form.
    $this->submitForm([], $this->fieldName . '_settings_edit');

    // Ensure that form displays correct set of fields.
    $this->session->checkboxChecked('Combine equivalent values');
    $this->session->checkboxNotChecked('Display FIELD value prefix and suffix');
    $this->session->checkboxNotChecked('Display FROM value prefix and suffix');
    $this->session->checkboxChecked('Display TO value prefix and suffix');
    $this->session->checkboxChecked('Display COMBINED value prefix and suffix');

    // Update formatter settings.
    $edit = [
      "fields[$this->fieldName][settings_edit_form][settings][range_combine]" => FALSE,
    ];
    $this->submitForm($edit, 'Update');

    // Ensure that summary is correct.
    $this->session->pageTextNotContains('Equivalent values will be combined into a single value.');
    $this->session->pageTextNotContains('Display with COMBINED value prefix and suffix.');
  }

}
