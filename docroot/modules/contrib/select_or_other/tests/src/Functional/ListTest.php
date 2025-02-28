<?php

namespace Drupal\Tests\select_or_other\Functional;

/**
 * Tests the the functionality of the Reference widget.
 *
 * @group select_or_other
 */
class ListTest extends TestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['options'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $field_settings = [];
    $widget = 'select_or_other_list';
    $widgets = ['select_or_other_select', 'select_or_other_buttons'];
    $this->prepareTestFields('list_string', $field_settings, $widget, $widgets);
    $user = $this->drupalCreateUser($this->defaultPermissions);
    $this->drupalLogin($user);
  }

  /**
   * Make sure an empty option is present when relevant.
   */
  public function testEmptyOption($empty_option = '') {
    parent::testEmptyOption('My cool new value');
  }

  /**
   * Test for illegal choice.
   */
  public function testIllegalChoice() {
    foreach ($this->fields as $field_name => $field) {
      $this->drupalGet('node/add/' . $this->getFieldContentType($field_name));
      $select_type = $field['select_type'];
      $multiple = $field['cardinality'] !== 1;
      $required = $field['required'];

      // Test checkbox/radios behaviour. Checkboxes/radios that are previewed
      // shouldn't get an error when returning to editing. Checkboxes with
      // multiple cardinality should be tested with other option checked and
      // without other option checked. Radios with single cardinality
      // shouldn't get an error when - none - option is checked and returned
      // from preview.
      if ($select_type === 'select_or_other_buttons') {
        if (!$required) {
          if ($multiple) {
            $this->setFieldValue($field_name, 'select_or_other', 'multi_value');
            $this->clickLink('Edit');

            $this->getSession()->getPage()->findButton('edit-preview')->press();
            $this->clickLink('Back to content editing');
            $this->assertSession()->pageTextNotContains('An illegal choice has been detected. Please contact the site administrator.');

            $this->setFieldValue($field_name, 'multi_value');
            $this->clickLink('Edit');

            $this->getSession()->getPage()->findButton('edit-preview')->press();
            $this->clickLink('Back to content editing');
            $this->assertSession()->pageTextNotContains('An illegal choice has been detected. Please contact the site administrator.');
          }
          else {
            $this->setFieldValue($field_name, 'select_or_other', 'value');
            $this->clickLink('Edit');

            $this->getSession()->getPage()->findButton('edit-preview')->press();
            $this->clickLink('Back to content editing');
            $this->assertSession()->pageTextNotContains('An illegal choice has been detected. Please contact the site administrator.');

            $this->setFieldValue($field_name, '');

            $this->clickLink('Back to content editing');
            $this->assertSession()->pageTextNotContains('An illegal choice has been detected. Please contact the site administrator.');
          }
        }
      }

    }
  }

}
