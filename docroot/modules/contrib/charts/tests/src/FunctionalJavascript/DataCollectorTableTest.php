<?php

namespace Drupal\Tests\charts\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\charts\Traits\ConfigUpdateTrait;
use Drupal\charts_test\Form\DataCollectorTableTestForm;

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

  const TABLE_COLUMN_SELECTOR = 'table[data-drupal-selector="edit-series-data-collector-table"] tbody tr:nth-child(1) td:not(.data-collector-table--row--delete):not(.data-collector-table--row-operations-cell)';

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
   * Test reorder operations.
   */
  public function testReorderOperations() {
    $this->drupalGet('/charts_test/data_collector_table_test_form');
    $page = $this->getSession()->getPage();

    // Fill Row 1 and Row 2 with identifiable values.
    $page->find('css', 'input[name="series[data_collector_table][0][0][data]"]')->setValue('A1');
    $page->find('css', 'input[name="series[data_collector_table][1][0][data]"]')->setValue('B1');

    // Move Row 1 (position 1) down.
    $this->doTableOperation('move_down', 'row', [1]);

    // Verify swap.
    $this->assertEquals('B1', $page->find('css', 'input[name="series[data_collector_table][0][0][data]"]')->getValue());
    $this->assertEquals('A1', $page->find('css', 'input[name="series[data_collector_table][1][0][data]"]')->getValue());

    // Move Row 2 (now A1) back up (position 1).
    $this->doTableOperation('move_up', 'row', [2]);

    // Verify swap back.
    $this->assertEquals('A1', $page->find('css', 'input[name="series[data_collector_table][0][0][data]"]')->getValue());
    $this->assertEquals('B1', $page->find('css', 'input[name="series[data_collector_table][1][0][data]"]')->getValue());

    // Test Column move.
    // Initially: Col 1, Col 2.
    $page->find('css', 'input[name="series[data_collector_table][0][0][data]"]')->setValue('C1');
    $page->find('css', 'input[name="series[data_collector_table][0][1][data]"]')->setValue('D1');

    // Move Col 1 (position 1) right.
    $this->doTableOperation('move_right', 'column', [1]);

    // Verify swap.
    $this->assertEquals('D1', $page->find('css', 'input[name="series[data_collector_table][0][0][data]"]')->getValue());
    $this->assertEquals('C1', $page->find('css', 'input[name="series[data_collector_table][0][1][data]"]')->getValue());

    // Move Col 2 (now C1) back left (position 1).
    $this->doTableOperation('move_left', 'column', [2]);

    // Verify swap back.
    $this->assertEquals('C1', $page->find('css', 'input[name="series[data_collector_table][0][0][data]"]')->getValue());
    $this->assertEquals('D1', $page->find('css', 'input[name="series[data_collector_table][0][1][data]"]')->getValue());
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
    if ($operation === 'add') {
      $value = ucfirst($operation) . ' ' . $on;
      $this->pressAjaxButton(static::TABLE_SELECTOR . ' input[value="' . $value . '"]');
      $on === 'row' ? $this->assertRowsIncreased() : $this->assertColumnsIncreased();
      return;
    }

    $on_row = $on === 'row';
    $counter = $on === 'row' ? DataCollectorTableTestForm::INITIAL_ROWS + 1 : DataCollectorTableTestForm::INITIAL_COLUMNS + 1;
    foreach ($positions as $position) {
      $this->executePositionedOperation($operation, $on, $position, $counter);
    }
  }

  /**
   * Executes an operation on a specific row or column position.
   *
   * @param string $operation
   *   The operation type (e.g., 'delete', 'move_up', 'move_down').
   * @param string $on
   *   The target type: 'row' or 'column'.
   * @param int $position
   *   The 1-indexed position of the row or column to operate on.
   * @param int $counter
   *   (optional) A counter passed by reference to track the total number of
   *   elements during deletion cycles.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   If the target container or button cannot be found.
   */
  protected function executePositionedOperation(string $operation, string $on, int $position, int &$counter = 0) {
    $on_row = $on === 'row';
    $container = $on_row ? $this->getRow($position) : $this->getColumnOperationCell($position);
    $label = $this->getOperationLabel($operation, $on);

    $container->pressButton($label);

    if ($operation === 'delete') {
      $locator = $on_row ? static::TABLE_ROW_SELECTOR : static::TABLE_COLUMN_SELECTOR;
      $this->assertDeletionOperation($counter, $locator);
    }
    else {
      $this->assertSession()->assertWaitOnAjaxRequest();
    }
  }

  /**
   * Gets a specific row from the table body.
   *
   * @param int $position
   *   The 1-indexed position of the row.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The row element.
   */
  protected function getRow(int $position): NodeElement {
    $rows = $this->getSession()->getPage()->findAll('css', static::TABLE_ROW_SELECTOR);
    $this->assertGreaterThanOrEqual($position, count($rows), "Row $position exists.");
    return $rows[$position - 1];
  }

  /**
   * Gets a specific cell from the column operations row.
   *
   * @param int $position
   *   The 1-indexed position of the column.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The cell element containing the column operations.
   */
  protected function getColumnOperationCell(int $position): NodeElement {
    $row = $this->getSession()->getPage()->find('css', static::TABLE_SELECTOR . ' .data-collector-table--column-deletes-row');
    $this->assertNotEmpty($row, 'Column operations row exists.');
    $cells = $row->findAll('css', 'td');
    $this->assertGreaterThanOrEqual($position, count($cells), "Column $position operation cell exists.");
    return $cells[$position - 1];
  }

  /**
   * Gets the button label (title) for a specific operation.
   *
   * @param string $operation
   *   The operation type.
   * @param string $on
   *   The target type ('row' or 'column').
   *
   * @return string
   *   The button title label.
   */
  protected function getOperationLabel(string $operation, string $on): string {
    return match ($operation) {
      'delete' => $on === 'row' ? 'Delete row' : 'Delete column',
      'move_up' => 'Move row up',
      'move_down' => 'Move row down',
      'move_left' => 'Move column left',
      'move_right' => 'Move column right',
      default => throw new \InvalidArgumentException("Unknown operation: $operation"),
    };
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
    $this->assertEquals($current_count, count($targets), "Expected $current_count elements after deletion, but found " . count($targets) . " using locator $locator");
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
