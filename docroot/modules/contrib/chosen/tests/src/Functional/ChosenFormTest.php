<?php

namespace Drupal\Tests\chosen\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Chosen form API test.
 *
 * @group chosen
 */
#[RunTestsInSeparateProcesses]
#[Group('chosen')]
class ChosenFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['chosen', 'chosen_test'];

  /**
   * Test the form page.
   */
  public function testFormPage() {
    $this->drupalGet('chosen-test');

    $this->assertSession()->pageTextContains('Select');
    $this->assertSession()->elementExists('css', 'select#edit-select.chosen-enable');

    $this->assertSession()->pageTextContains('Select overridden');
    $this->assertSession()->elementExists('css', 'select#edit-select-overridden.chosen-enable');
    $this->assertSession()->elementAttributeContains('css', '#edit-select-overridden', 'data-placeholder', 'Pick an option');
    $this->assertSession()->elementAttributeContains('css', '#edit-select-overridden', 'data-no_results_text', 'Nothing matched');
    $this->assertSession()->elementAttributeContains('css', '#edit-select-overridden', 'data-search_contains', '1');
  }

}
