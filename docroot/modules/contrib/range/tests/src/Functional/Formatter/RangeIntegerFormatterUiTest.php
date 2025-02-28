<?php

namespace Drupal\Tests\range\Functional\Formatter;

/**
 * Tests the integer field formatter UI.
 *
 * @group range
 */
class RangeIntegerFormatterUiTest extends RangeFormatterUiTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->fieldType = 'range_integer';
    $this->formatterType = 'range_integer';

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
    $this->session->pageTextContains('1234-4321');

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
    $this->session->fieldNotExists('Decimal marker');
    $this->session->fieldNotExists('Scale');
    $this->session->fieldNotExists('Format');

    // Update formatter settings.
    $edit = [
      "fields[$this->fieldName][settings_edit_form][settings][range_separator]" => '|',
      "fields[$this->fieldName][settings_edit_form][settings][thousand_separator]" => ' ',
    ];
    $this->submitForm($edit, 'Update');

    // Ensure that summary is correct.
    $this->session->pageTextContains('1 234|4 321');

    // Go to formatter settings form.
    $this->submitForm([], $this->fieldName . '_settings_edit');

    // Ensure that form displays correct set of fields.
    $this->session->fieldValueEquals('Range separator', '|');
    $this->session->fieldValueEquals('Thousand marker', ' ');
  }

}
