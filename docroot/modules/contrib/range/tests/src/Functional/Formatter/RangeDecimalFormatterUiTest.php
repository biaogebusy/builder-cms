<?php

namespace Drupal\Tests\range\Functional\Formatter;

/**
 * Tests the decimal field formatter UI.
 *
 * @group range
 */
class RangeDecimalFormatterUiTest extends RangeFormatterUiTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->fieldType = 'range_decimal';
    $this->formatterType = 'range_decimal';

    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  public function testFormatterUi() {
    parent::testFormatterUi();

    $this->drupalGet('entity_test/structure/entity_test/display');

    // Enable Default formatter.
    $edit = [
      "fields[$this->fieldName][parent]" => 'content',
      "fields[$this->fieldName][region]" => 'content',
      "fields[$this->fieldName][type]" => $this->formatterType,
    ];
    $this->submitForm($edit, 'Save');

    // Ensure that default summary is correct.
    $this->session->pageTextContains('1234.12-4321.10');

    // Go to formatter settings form.
    $this->submitForm([], $this->fieldName . '_settings_edit');

    // Ensure that form displays correct set of fields.
    $this->session->fieldValueEquals('Range separator', '-');
    $this->session->fieldValueEquals('Thousand marker', '');
    $this->session->optionExists('Thousand marker', '- None -');
    $this->session->optionExists('Thousand marker', 'Decimal point');
    $this->session->optionExists('Thousand marker', 'Comma');
    $this->session->optionExists('Thousand marker', 'Space');
    $this->session->optionExists('Thousand marker', 'Thin space');
    $this->session->optionExists('Thousand marker', 'Apostrophe');
    $this->session->fieldValueEquals('Decimal marker', '.');
    $this->session->optionExists('Decimal marker', 'Decimal point');
    $this->session->optionExists('Decimal marker', 'Comma');
    $this->session->fieldValueEquals('Scale', 2);
    foreach (range(0, 10) as $option) {
      $this->session->optionExists('Scale', $option);
    }
    $this->session->fieldNotExists('Format');

    // Update formatter settings.
    $edit = [
      "fields[$this->fieldName][settings_edit_form][settings][range_separator]" => '|',
      "fields[$this->fieldName][settings_edit_form][settings][thousand_separator]" => ' ',
      "fields[$this->fieldName][settings_edit_form][settings][decimal_separator]" => ',',
      "fields[$this->fieldName][settings_edit_form][settings][scale]" => 4,
    ];
    $this->submitForm($edit, 'Update');

    // Ensure that summary is correct.
    $this->session->pageTextContains('1 234,1235|4 321,0988');

    // Go to formatter settings form.
    $this->submitForm([], $this->fieldName . '_settings_edit');

    // Ensure that form displays correct set of fields.
    $this->session->fieldValueEquals('Range separator', '|');
    $this->session->fieldValueEquals('Thousand marker', ' ');
    $this->session->fieldValueEquals('Decimal marker', ',');
    $this->session->fieldValueEquals('Scale', 4);
  }

}
