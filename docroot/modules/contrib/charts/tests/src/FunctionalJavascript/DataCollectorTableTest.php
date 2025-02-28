<?php

namespace Drupal\Tests\charts\FunctionalJavascript;

use Drupal\charts_test\Form\DataCollectorTableTestForm;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\charts\Traits\ConfigUpdateTrait;

/**
 * Tests the data collector table element.
 *
 * @group charts
 */
class DataCollectorTableTest extends WebDriverTestBase {

  use ConfigUpdateTrait;
  use StringTranslationTrait;

  /**
   * Default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * List modules.
   *
   * @var array
   */
  protected static $modules = [
    'charts',
    'charts_test',
  ];

  const TABLE_SELECTOR = 'table[data-drupal-selector="edit-series-data-collector-table"]';

  const TABLE_ROW_SELECTOR = 'table[data-drupal-selector="edit-series-data-collector-table"] tr.data-collector-table--row';

  const TABLE_COLUMN_SELECTOR = 'table[data-drupal-selector="edit-series-data-collector-table"] tbody tr:nth-child(1) td:not(.data-collector-table--row--delete)';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->updateFooConfiguration('bar');
  }

  /**
   * Tests the data collector table.
   */
  public function testDataCollectorTable() {
    $this->drupalGet('/charts_test/data_collector_table_test_form');
    $table_id_selector = static::TABLE_SELECTOR;

    // Count generated rows.
    $rows = $this->cssSelect(static::TABLE_ROW_SELECTOR);
    $this->assertTrue(count($rows) === DataCollectorTableTestForm::INITIAL_ROWS, 'Expected rows were found.');

    // Count generated columns.
    // Only using the first row to count columns.
    $columns = $this->cssSelect(static::TABLE_COLUMN_SELECTOR);
    $this->assertTrue(count($columns) === DataCollectorTableTestForm::INITIAL_COLUMNS, 'Expected columns were found.');

    // Fill the table.
    $cell_input_selector = $table_id_selector . ' tr.data-collector-table--row td > .data-collector-table--row--cell .form-text';
    $cell_inputs = $this->cssSelect($cell_input_selector);
    $this->assertNotEmpty($cell_inputs, $this->t('@count inputs were found', [
      '@count' => count($cell_inputs),
    ]));
    $this->fillInputs($cell_inputs);

    // Test adding a row.
    $this->doTableOperation('add', 'row');
    // The New row should not have any data.
    $this->assertNewCellIsEmpty('row');

    // Test adding a column.
    $this->doTableOperation('add', 'column');
    // The New column should not have any data.
    $this->assertNewCellIsEmpty('column');

    // Delete the second and third row.
    $this->doTableOperation('delete', 'row', [2, 3]);
    // Remove the middle column.
    $this->doTableOperation('delete', 'column', [2]);

    // Import csv from resources.
    // Opening the dom "details" element.
    $series_import_details = $this->getSession()->getPage()->find('css', 'details[data-drupal-selector="edit-series-import"]');
    $this->assertFalse($series_import_details->hasAttribute('open'), 'Details closed');
    $this->getSession()->executeScript("document.querySelector('details[data-drupal-selector=edit-series-import]').setAttribute('open', true);");
    $this->assertTrue($series_import_details->hasAttribute('open'), 'Details open');
    // Add the CSV file containing the test data.
    $file_field_name = 'files[series]';
    $filename = $this->getResourcePath() . '/csv/first_column.csv';
    $this->getSession()->getPage()->attachFileToField($file_field_name, $filename);
    // Submit upload the file and wait for ajax processing.
    $button_selector = 'details[data-drupal-selector="edit-series-import"] input[value="Upload CSV"]';
    $this->pressAjaxButton($button_selector);
    $this->assertSession()->assertWaitOnAjaxRequest(20000);

    // Verify the uploaded data.
    $page = $this->getSession()->getPage();
    $targets = $page->findAll('css', static::TABLE_ROW_SELECTOR);
    $this->assertEquals(7, count($targets), 'The count of number of expected rows match.');
    $targets = $page->findAll('css', static::TABLE_COLUMN_SELECTOR);
    $this->assertEquals(3, count($targets), 'The count of number of expected columns match.');
    $cell_input = $page->find('css', 'input[name="series[data_collector_table][0][0][data]"]');
    $this->assertEquals('Categories', $cell_input->getValue(), 'The cell value of row 1 at column 1 match.');
    $cell_input = $page->find('css', 'input[name="series[data_collector_table][6][2][data]"]');
    $this->assertEquals(234, $cell_input->getValue(), 'The last uploaded value in the cell match.');
  }

  /**
   * Tests the default colors pn the "chart_data_collector_table" element.
   */
  public function testColorDefaultColors() {
    $chart_config = \Drupal::config('charts.settings');
    $default_colors = $chart_config->get('charts_default_settings.display.colors');
    $this->drupalGet('/charts_test/data_collector_table_test_form');
    $page = $this->getSession()->getPage();

    // Get the first row, then inside the first row get the color input and
    // check its value.
    $first_row_color_input = $page->find('css', static::TABLE_ROW_SELECTOR . ':first-child td:nth-child(2) input[type="color"]');
    $this->assertEquals($default_colors[0], $first_row_color_input->getValue());

    // Adding one column.
    $this->doTableOperation('add', 'column');

    // Checking if the added column also has the expected color.
    $first_row_color_input = $page->find('css', static::TABLE_ROW_SELECTOR . ':first-child td:nth-child(3) input[type="color"]');
    $this->assertEquals($default_colors[1], $first_row_color_input->getValue());
  }

  /**
   * Do table operation.
   *
   * @param string $operation
   *   The operation.
   * @param string $on
   *   The element.
   * @param array $positions
   *   The position.
   */
  protected function doTableOperation(string $operation, string $on, array $positions = []) {
    $value = ucfirst($operation) . ' ' . $on;

    if ($operation === 'add') {
      $selector = static::TABLE_SELECTOR . ' input[value="' . $value . '"]';
      $this->pressAjaxButton($selector);

      if ($on === 'row') {
        $this->assertRowsIncreased();
      }
      else {
        $this->assertColumnsIncreased();
      }
    }
    else {
      $on_row = $on === 'row';
      if ($on_row) {
        $counter = DataCollectorTableTestForm::INITIAL_ROWS + 1;
        $locator = static::TABLE_ROW_SELECTOR;
      }
      else {
        $counter = DataCollectorTableTestForm::INITIAL_COLUMNS + 1;
        $locator = static::TABLE_COLUMN_SELECTOR;
      }
      foreach ($positions as $position) {
        if ($on_row) {
          $button_selector = static::TABLE_ROW_SELECTOR . ':nth-child(' . $position . ') .data-collector-table--row--delete input[value="Delete row"]';
        }
        else {
          $button_selector = static::TABLE_SELECTOR . ' .data-collector-table--column-deletes-row';
          $button_selector .= ' .data-collector-table--column--delete:nth-child(' . $position . ') input[value="Delete column"]';
        }
        $this->pressAjaxButton($button_selector);
        $this->assertDeletionOperation($counter, $locator);
      }
    }
  }

  /**
   * Assert rows.
   */
  protected function assertRowsIncreased() {
    $page = $this->getSession()->getPage();
    $this->assertTrue($page->waitFor(10, function ($page) {
      $expected_rows = DataCollectorTableTestForm::INITIAL_ROWS + 1;
      $rows = $page->findAll('css', static::TABLE_ROW_SELECTOR);
      return count($rows) === $expected_rows;
    }), 'Expected rows were increased by one after add row click.');
  }

  /**
   * Assert columns.
   */
  protected function assertColumnsIncreased() {
    $page = $this->getSession()->getPage();
    $this->assertTrue($page->waitFor(10, function ($page) {
      $expected_rows = DataCollectorTableTestForm::INITIAL_COLUMNS + 1;
      $columns = $page->findAll('css', static::TABLE_COLUMN_SELECTOR);
      return count($columns) === $expected_rows;
    }), 'Expected columns were increased by one after add column click.');
  }

  /**
   * Assert new cell.
   *
   * @param string $on
   *   The element.
   */
  protected function assertNewCellIsEmpty(string $on) {
    $page = $this->getSession()->getPage();
    if ($on === 'row') {
      $counter = DataCollectorTableTestForm::INITIAL_ROWS + 1;
      $selector = static::TABLE_ROW_SELECTOR . ':nth-child(' . $counter . ') td:first-child input';
    }
    else {
      $counter = DataCollectorTableTestForm::INITIAL_COLUMNS + 1;
      $selector = static::TABLE_COLUMN_SELECTOR . ':nth-child(' . $counter . ') input';
    }
    $cell_input = $page->find('css', $selector);
    $this->assertEmpty($cell_input->getValue(), 'Added row cells are empty.');
  }

  /**
   * Assert Deletion operation.
   *
   * @param int $current_count
   *   Current count.
   * @param string $locator
   *   The locator.
   */
  protected function assertDeletionOperation(int &$current_count, string $locator) {
    $web_assert = $this->assertSession();
    $web_assert->assertWaitOnAjaxRequest();
    $current_count--;
    $page = $this->getSession()->getPage();
    $targets = $page->findAll('css', $locator);
    $this->assertTrue(count($targets) === $current_count);
  }

  /**
   * Press the ajax button.
   *
   * @param string $selector
   *   The selector.
   */
  protected function pressAjaxButton(string $selector) {
    $button = $this->assertSession()->waitForElementVisible('css', $selector);
    $this->assertNotEmpty($button);
    $button->click();
  }

  /**
   * Fill inputs.
   *
   * @param array $inputs
   *   Input to fill.
   *
   * @return \Behat\Mink\Element\NodeElement[]
   *   The node element.
   */
  protected function fillInputs(array $inputs) {
    /** @var \Behat\Mink\Element\NodeElement[] $inputs */
    foreach ($inputs as $input) {
      $value = rand(0, count($inputs));
      $input->setValue((string) $value);
    }
    return $inputs;
  }

  /**
   * Get the path of the resource.
   *
   * @return string
   *   The resource folder path.
   */
  protected function getResourcePath() {
    return \Drupal::root() . '/' . \Drupal::service('extension.list.module')->getPath('charts') . '/tests/resources';
  }

}
