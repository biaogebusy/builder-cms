<?php

namespace Drupal\Tests\range\Functional\Formatter;

/**
 * Tests the unformatted field formatter UI.
 *
 * @group range
 */
class RangeUnformattedFormatterUiTest extends RangeFormatterUiTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->fieldType = 'range_float';
    $this->formatterType = 'range_unformatted';

    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  public function testFormatterUi() {
    parent::testFormatterUi();

    $this->drupalGet('entity_test/structure/entity_test/display');

    // Enable Unformatted formatter.
    $edit = [
      "fields[$this->fieldName][parent]" => 'content',
      "fields[$this->fieldName][region]" => 'content',
      "fields[$this->fieldName][type]" => $this->formatterType,
    ];
    $this->submitForm($edit, 'Save');

    // Ensure that summary is correct.
    $this->session->pageTextContains('1234.123456789-4321.0987654321');

    // Go to formatter settings form.
    $this->submitForm([], $this->fieldName . '_settings_edit');

    // Ensure that form displays correct set of fields.
    $this->session->fieldValueEquals('Range separator', '-');
    $this->session->fieldNotExists('Thousand marker');
    $this->session->fieldNotExists('Decimal marker');
    $this->session->fieldNotExists('Scale');
    $this->session->fieldNotExists('Format');

    // Update formatter settings.
    $edit = [
      "fields[$this->fieldName][settings_edit_form][settings][range_separator]" => '=',
    ];
    $this->submitForm($edit, 'Save');

    // Ensure that summary is correct.
    $this->session->pageTextContains('1234.123456789=4321.0987654321');

    // Go to formatter settings form.
    $this->submitForm([], $this->fieldName . '_settings_edit');

    // Ensure that form displays correct set of fields.
    $this->session->fieldValueEquals('Range separator', '=');
  }

}
