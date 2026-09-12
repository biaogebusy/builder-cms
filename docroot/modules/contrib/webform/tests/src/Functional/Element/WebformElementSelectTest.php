<?php

namespace Drupal\Tests\webform\Functional\Element;

/**
 * Tests for select element.
 *
 * @group webform
 */
class WebformElementSelectTest extends WebformElementBrowserTestBase {

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_element_select'];

  /**
   * Test select element.
   */
  public function testSelectElement(): void {
    $assert_session = $this->assertSession();

    // Check default empty option always included.
    $this->drupalGet('/webform/test_element_select');
    $this->assertTrue($assert_session->optionExists('select_empty_option_optional', '- None -')->isSelected());
    $this->assertFalse($assert_session->optionExists('select_empty_option_optional_default_value', '- None -')->isSelected());
    $this->assertTrue($assert_session->optionExists('select_empty_option_required', '- Select -')->isSelected());

    // Disable default empty option.
    \Drupal::configFactory()->getEditable('webform.settings')
      ->set('element.default_empty_option', FALSE)
      ->save();

    // Check default empty option is not always included.
    $this->drupalGet('/webform/test_element_select');
    $assert_session->optionNotExists('select_empty_option_optional', '- None -');
    $assert_session->optionNotExists('select_empty_option_optional_default_value', '- None -');
    $this->assertTrue($assert_session->optionExists('select_empty_option_required', '- Select -')->isSelected());

    // Set custom empty option values.
    \Drupal::configFactory()->getEditable('webform.settings')
      ->set('element.default_empty_option', TRUE)
      ->set('element.default_empty_option_required', '{required}')
      ->set('element.default_empty_option_optional', '{optional}')
      ->save();

    // Check customize empty option displayed.
    $this->drupalGet('/webform/test_element_select');
    $this->assertTrue($assert_session->optionExists('select_empty_option_optional', '{optional}')->isSelected());
    $this->assertFalse($assert_session->optionExists('select_empty_option_optional_default_value', '{optional}')->isSelected());
    $this->assertTrue($assert_session->optionExists('select_empty_option_required', '{required}')->isSelected());
  }

}
