<?php

namespace Drupal\Tests\views_bulk_operations\FunctionalJavaScript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\views_bulk_operations\Form\ViewsBulkOperationsFormTrait;

/**
 * @coversDefaultClass \Drupal\views_bulk_operations\Plugin\views\field\ViewsBulkOperationsBulkForm
 * @group views_bulk_operations
 */
class ViewsBulkOperationsBulkFormTest extends WebDriverTestBase {

  use ViewsBulkOperationsFormTrait;

  const TEST_NODE_COUNT = 15;

  const TEST_VIEW_ID = 'views_bulk_operations_test';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';


  /**
   * The assert session.
   *
   * @var \Drupal\Tests\WebAssert
   */
  protected $assertSession;

  /**
   * The page element.
   *
   * @var \Behat\Mink\Element\DocumentElement
   */
  protected $page;


  /**
   * The selected indexes of rows.
   *
   * @var array
   */
  protected $selectedIndexes = [];

  /**
   * Test nodes.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $testNodes = [];

  /**
   * Test view parameters as in the config.
   *
   * @var array
   */
  protected $testViewParams;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = [
    'node',
    'views',
    'views_bulk_operations',
    'views_bulk_operations_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create some nodes for testing.
    $this->drupalCreateContentType(['type' => 'page']);
    for ($i = 0; $i <= self::TEST_NODE_COUNT; $i++) {
      $node = $this->drupalCreateNode([
        'type' => 'page',
        'title' => 'Title ' . $i,
      ]);
    }
    $admin_user = $this->drupalCreateUser(
      [
        'edit any page content',
        'create page content',
        'delete any page content',
      ]);
    $this->drupalLogin($admin_user);

    $this->assertSession = $this->assertSession();
    $this->page = $this->getSession()->getPage();

    // Get useful config data from the test view.
    $config_data = \Drupal::service('config.factory')->get('views.view.' . self::TEST_VIEW_ID)->getRawData();
    $this->testViewParams = [
      'items_per_page' => $config_data['display']['default']['display_options']['pager']['options']['items_per_page'],
      'path' => $config_data['display']['page_1']['display_options']['path'],
    ];

    $this->drupalGet('/' . $this->testViewParams['path']);
  }

  /**
   * Tests the VBO bulk form without dynamic insertion.
   */
  public function testViewsBulkOperationsAjaxUi() {
    // Make sure a checkbox appears on all rows and the button exists.
    $this->assertSession->buttonExists('Simple test action');
    for ($i = 0; $i < $this->testViewParams['items_per_page']; $i++) {
      $this->assertSession->fieldExists('edit-views-bulk-operations-bulk-form-' . $i);
    }

    // Select some items on the first page.
    foreach ([0, 1, 3] as $selected_index) {
      $this->selectedIndexes[] = $selected_index;
      $this->page->checkField('edit-views-bulk-operations-bulk-form-' . $selected_index);
    }

    // Go to the next page and select some more.
    $this->page->clickLink('Go to next page');
    foreach ([1, 2] as $selected_index) {
      // This is page one so indexes are incremented by page count and
      // checkbox selectors start from 0 again.
      $this->selectedIndexes[] = $selected_index + $this->testViewParams['items_per_page'];
      $this->page->checkField('edit-views-bulk-operations-bulk-form-' . $selected_index);
    }

    // Execute test operation.
    $this->page->pressButton('Simple test action');

    // Assert if only the selected nodes were processed.
    foreach ($this->testNodes as $delta => $node) {
      if (in_array($delta, $this->selectedIndexes, TRUE)) {
        $this->assertSession->pageTextContains(sprintf('Test action (preconfig: Test setting, label: %s)', $node->label()));
      }
      else {
        $this->assertSession->pageTextNotContains(sprintf('Test action (preconfig: Test setting, label: %s)', $node->label()));
      }
    }
    $this->assertSession->pageTextContains(sprintf('Action processing results: Test (%s)', count($this->selectedIndexes)));

  }

  /**
   * Tests the VBO bulk form with dynamic insertion.
   *
   * Nodes inserted right after selecting targeted row(s) of the view.
   */
  public function testViewsBulkOperationsWithDynamicInsertion() {

    $this->selectedIndexes = [0, 1, 3];

    foreach ($this->selectedIndexes as $selected_index) {
      $this->page->checkField('edit-views-bulk-operations-bulk-form-' . $selected_index);
    }

    // Insert nodes.
    $nodes = [];
    for ($i = 100; $i < 100 + self::TEST_NODE_COUNT; $i++) {
      $nodes[] = $this->drupalCreateNode([
        'type' => 'page',
        'title' => 'Title ' . $i,
      ]);
    }

    $this->page->pressButton('Simple test action');

    foreach ($this->selectedIndexes as $index) {
      $this->assertSession->pageTextContains(sprintf('Test action (preconfig: Test setting, label: Title %s)', self::TEST_NODE_COUNT - $index));
    }
    $this->assertSession->pageTextContains(sprintf('Action processing results: Test (%s)', count($this->selectedIndexes)));
  }

}
