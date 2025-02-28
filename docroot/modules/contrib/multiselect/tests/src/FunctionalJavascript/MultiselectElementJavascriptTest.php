<?php

namespace Drupal\Tests\multiselect\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the multiselect element using JavaScript.
 *
 * @group multiselect
 */
class MultiselectElementJavascriptTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['multiselect', 'multiselect_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Disable configuration schema checking for now.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Tests selection and deselection of multiselect element.
   */
  public function testSelection() {
    $this->drupalGet('/multiselect_test/page');
    $wrapper_selector = '.js-form-item-no-default';

    $available_selector = "$wrapper_selector #edit-no-default-available";
    $available_options = [
      'apple' => 'Apple',
      'banana' => 'Banana',
      'cherry' => 'Cherry',
    ];
    $this->assertOptions($available_selector, $available_options);

    $selected_selector = "$wrapper_selector #edit-no-default";
    $selected_options = [];
    $this->assertOptions($selected_selector, $selected_options);

    $driver = $this->getSession()->getDriver();
    $driver->selectOption($this->cssSelectToXpath($available_selector), 'apple');
    $driver->click($this->cssSelectToXpath("$wrapper_selector .multiselect-add"));

    unset($available_options['apple']);
    $selected_options['apple'] = 'Apple';
    $this->assertOptions($available_selector, $available_options);
    $this->assertOptions($selected_selector, $selected_options);

    $driver->selectOption($this->cssSelectToXpath($available_selector), 'cherry');
    $driver->click($this->cssSelectToXpath("$wrapper_selector .multiselect-add"));

    unset($available_options['cherry']);
    $selected_options['cherry'] = 'Cherry';
    $this->assertOptions($available_selector, $available_options);
    $this->assertOptions($selected_selector, $selected_options);

    $driver->selectOption($this->cssSelectToXpath($selected_selector), 'apple');
    $driver->click($this->cssSelectToXpath("$wrapper_selector .multiselect-remove"));

    $available_options['apple'] = 'Apple';
    unset($selected_options['apple']);
    $this->assertOptions($available_selector, $available_options);
    $this->assertOptions($selected_selector, $selected_options);

    $driver->selectOption($this->cssSelectToXpath($selected_selector), 'cherry');
    $driver->click($this->cssSelectToXpath("$wrapper_selector .multiselect-remove"));

    $available_options['cherry'] = 'Cherry';
    unset($selected_options['cherry']);
    $this->assertOptions($available_selector, $available_options);
    $this->assertOptions($selected_selector, $selected_options);
  }

  /**
   * Tests the default values of multiselect elements.
   */
  public function testDefaultValue() {
    $this->drupalGet('/multiselect_test/page');

    $options = [];
    $this->assertOptions('.js-form-item-no-default #edit-no-default', $options);

    $options['banana'] = 'Banana';
    $this->assertOptions('.js-form-item-single-default #edit-single-default', $options);

    $options['cherry'] = 'Cherry';
    $this->assertOptions('.js-form-item-multiple-default #edit-multiple-default', $options);
  }

  /**
   * Asserts that the given options are contained in a certain select element.
   *
   * @param string $selector
   *   The CSS selector of the select element that contains the options.
   * @param array $options
   *   An array of options where the array keys are the option values and the
   *   array values are the option labels.
   */
  protected function assertOptions($selector, array $options) {
    $this->assertSession()->elementsCount('css', "$selector option", count($options));
    foreach ($options as $value => $label) {
      $option_selector = "$selector option[value=$value]";
      $this->assertSession()->elementExists('css', $option_selector);
      $this->assertSession()->elementTextContains('css', $option_selector, $label);
    }
  }

}
