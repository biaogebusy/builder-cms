<?php

namespace Drupal\Tests\views_infinite_scroll\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Basic integration smoke test for the pager plugin.
 *
 * @group views_infinite_scroll
 */
class IntegrationSmokeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['views', 'views_ui', 'views_infinite_scroll'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalLogin($this->createUser(['administer views']));
  }

  /**
   * Test the views plugin.
   */
  public function testPlugin() {
    // Create a view with the pager plugin enabled.
    $this->drupalGet('admin/structure/views/add');
    $this->submitForm([
      'label' => 'Test Plugin',
      'id' => 'test_plugin',
      'page[create]' => '1',
      'page[title]' => 'Test Plugin',
      'page[path]' => 'test-plugin',
    ], 'Save and edit');
    $this->clickLink('Mini');
    $this->submitForm([
      'pager[type]' => 'infinite_scroll',
    ], 'Apply');
    $this->submitForm([
      'pager_options[views_infinite_scroll][button_text]' => 'More Please',
      'pager_options[views_infinite_scroll][automatically_load_content]' => '',
    ], 'Apply');
    $this->assertSession()->linkExists('Infinite Scroll');
    $this->assertSession()->pageTextContains('Click to load, 10 items');
    $this->submitForm([], 'Save');

    // Open the permissions to view the page.
    $this->clickLink('Permission');
    $this->submitForm([
      'access[type]' => 'none',
    ], 'Apply');
    $this->submitForm([], 'Save');

    // Ensure the wrapper div appears on the page.
    $this->drupalGet('test-plugin');
    $this->assertSession()->responseContains('data-drupal-views-infinite-scroll-content-wrapper');
  }

}
