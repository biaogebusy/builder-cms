<?php

declare(strict_types=1);

namespace Drupal\Tests\charts\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\charts\Element\ChartDataCollectorTable;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the ChartDataCollectorTable class.
 *
 * @group charts
 *
 * @coversDefaultClass \Drupal\charts\Element\ChartDataCollectorTable
 */
class ChartDataCollectorTableTest extends UnitTestCase {

  /**
   * The ChartDataCollectorTable instance.
   *
   * @var \Drupal\charts\Element\ChartDataCollectorTable
   */
  protected ChartDataCollectorTable $table;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $element_info_manager = $this->prophesize('Drupal\Core\Render\ElementInfoManagerInterface');
    $container->set('plugin.manager.element_info', $element_info_manager->reveal());
    \Drupal::setContainer($container);

    $configuration = [];
    $plugin_id = 'charts_chart';
    $plugin_definition = [];

    $this->table = new ChartDataCollectorTable($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Tests the getInfo() method.
   *
   * @covers ::getInfo
   */
  public function testGetInfo(): void {
    $info = $this->table->getInfo();
    $this->assertIsArray($info);
    $this->assertCount(14, $info);
    $this->assertArrayHasKey('#theme_wrappers', $info);
    $this->assertArrayHasKey('#series_type_options', $info);
  }

  /**
   * Tests processDataCollectorTable().
   *
   * @covers ::processDataCollectorTable
   */
  public function testProcessDataCollectorTable(): void {

    $element = [
      '#parents' => ['charts', 'chart'],
      '#default_colors' => [],
      '#import_csv' => TRUE,
      '#initial_columns' => 3,
      '#initial_rows' => 10,
      '#table_drag' => TRUE,
      '#table_wrapper' => '',
      '#value' => [],
    ];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->exactly(2))
      ->method('getStorage')
      ->willReturn([
        'data_collector_table' => [
          '#parents' => ['charts', 'chart'],
        ],
      ]);

    $complete_form = [];

    $this->table->processDataCollectorTable($element, $form_state, $complete_form);
  }

  /**
   * Tests processDataCollectorTable().
   *
   * @covers ::processDataCollectorTable
   */
  public function testProcessDataCollectorTableEmptyCollector(): void {

    $element = [
      '#parents' => ['charts', 'chart'],
      '#default_colors' => [],
      '#import_csv' => TRUE,
      '#initial_columns' => 3,
      '#initial_rows' => 10,
      '#table_drag' => TRUE,
      '#table_wrapper' => '',
      '#value' => [],
    ];
    $form_state = $this->createMock('Drupal\Core\Form\FormStateInterface');
    $form_state->expects($this->exactly(2))
      ->method('getStorage')
      ->willReturn([
        'charts' => [
          'data_collector_table' => [
            '#parents' => ['charts', 'chart'],
          ],
        ],
        'data_collector_table' => [
          '#parents' => ['charts', 'chart'],
        ],
        'table_categories_identifier' => [
          'chart' => [
            'categories' => [],
          ],
        ],
      ]);

    $complete_form = [];

    $this->table->processDataCollectorTable($element, $form_state, $complete_form);
  }

  /**
   * Tests retaining colors after a CSV import.
   *
   * @param array $new_table
   *   The freshly imported table, without colors.
   * @param array $existing_table
   *   The table as it was before the import.
   * @param string $type
   *   Either self::FIRST_COLUMN or self::FIRST_ROW.
   * @param array $expected_colors
   *   Expectations keyed by "row:column". A NULL value means the cell must not
   *   carry a color key at all.
   *
   * @covers ::retainExistingColors
   *
   * @dataProvider providerRetainExistingColors
   */
  public function testRetainExistingColors(array $new_table, array $existing_table, string $type, array $expected_colors): void {
    ChartDataCollectorTable::retainExistingColors($new_table, $existing_table, $type);

    foreach ($expected_colors as $coordinate => $expected) {
      [$row, $column] = array_map('intval', explode(':', $coordinate));
      $cell = $new_table[$row][$column];
      if ($expected === NULL) {
        $this->assertArrayNotHasKey('color', $cell, "Cell {$coordinate} should not have a color.");
      }
      else {
        $this->assertSame($expected, $cell['color'], "Cell {$coordinate} has the wrong color.");
      }
    }
  }

  /**
   * Provides test cases for ::testRetainExistingColors().
   *
   * @return array
   *   An array of test cases.
   */
  public static function providerRetainExistingColors(): array {
    return [
      'first column: series colors live on the header row' => [
        // New table: more rows than before, no colors.
        [
          [['data' => 'Categories'], ['data' => 'Players'], ['data' => 'Coaches']],
          [['data' => 'a'], ['data' => '50'], ['data' => '50']],
          [['data' => 'b'], ['data' => '60'], ['data' => '80']],
        ],
        // Existing table.
        [
          [
            ['data' => 'Categories'],
            ['data' => 'Players', 'color' => '#111111'],
            ['data' => 'Coaches', 'color' => '#222222'],
          ],
          [['data' => 'a'], ['data' => '50'], ['data' => '50']],
        ],
        ChartDataCollectorTable::FIRST_COLUMN,
        [
          '0:1' => '#111111',
          '0:2' => '#222222',
          // The category cell and the data cells never receive a color.
          '0:0' => NULL,
          '1:1' => NULL,
          '2:2' => NULL,
        ],
      ],

      'first row: every series row keeps its own color' => [
        [
          [['data' => 'Categories'], ['data' => 'Jan'], ['data' => 'Feb']],
          [['data' => 'S1'], ['data' => '10'], ['data' => '20']],
          [['data' => 'S2'], ['data' => '30'], ['data' => '40']],
          [['data' => 'S3'], ['data' => '50'], ['data' => '60']],
        ],
        [
          [['data' => 'Categories'], ['data' => 'Jan'], ['data' => 'Feb']],
          [['data' => 'S1', 'color' => '#aaaaaa'], ['data' => '10'], ['data' => '20']],
          [['data' => 'S2', 'color' => '#bbbbbb'], ['data' => '30'], ['data' => '40']],
          [['data' => 'S3', 'color' => '#cccccc'], ['data' => '50'], ['data' => '60']],
        ],
        ChartDataCollectorTable::FIRST_ROW,
        [
          // The case the previous approach only got right for the first row.
          '1:0' => '#aaaaaa',
          '2:0' => '#bbbbbb',
          '3:0' => '#cccccc',
          // The corner cell, the category cells and the data cells stay bare.
          '0:0' => NULL,
          '0:1' => NULL,
          '1:1' => NULL,
        ],
      ],

      'first row: import carries more series than existed' => [
        [
          [['data' => 'Categories'], ['data' => 'Jan']],
          [['data' => 'S1'], ['data' => '10']],
          [['data' => 'S2'], ['data' => '30']],
          [['data' => 'S3'], ['data' => '50']],
          [['data' => 'S4'], ['data' => '70']],
        ],
        [
          [['data' => 'Categories'], ['data' => 'Jan']],
          [['data' => 'S1', 'color' => '#aaaaaa'], ['data' => '10']],
          [['data' => 'S2', 'color' => '#bbbbbb'], ['data' => '30']],
        ],
        ChartDataCollectorTable::FIRST_ROW,
        [
          '1:0' => '#aaaaaa',
          '2:0' => '#bbbbbb',
          // New series have no color to restore and are left untouched.
          '3:0' => NULL,
          '4:0' => NULL,
        ],
      ],

      'no existing table: nothing to restore' => [
        [
          [['data' => 'Categories'], ['data' => 'Players']],
          [['data' => 'a'], ['data' => '50']],
        ],
        [],
        ChartDataCollectorTable::FIRST_COLUMN,
        [
          '0:0' => NULL,
          '0:1' => NULL,
          '1:1' => NULL,
        ],
      ],
    ];
  }

  /**
   * Tests that an empty imported table is left empty.
   *
   * @covers ::retainExistingColors
   */
  public function testRetainExistingColorsWithEmptyNewTable(): void {
    $existing_table = [
      [['data' => 'Categories'], ['data' => 'Players', 'color' => '#111111']],
    ];
    $new_table = [];

    ChartDataCollectorTable::retainExistingColors($new_table, $existing_table, ChartDataCollectorTable::FIRST_COLUMN);

    $this->assertSame([], $new_table);
  }

  /**
   * Tests the swapRows() method.
   *
   * @covers ::swapRows
   */
  public function testSwapRows(): void {
    $table = [
      0 => ['data' => 'row 0', 'weight' => 0],
      1 => ['data' => 'row 1', 'weight' => 1],
    ];

    ChartDataCollectorTable::swapRows($table, 0, 1);

    $this->assertEquals('row 1', $table[0]['data']);
    $this->assertEquals(0, $table[0]['weight']);
    $this->assertEquals('row 0', $table[1]['data']);
    $this->assertEquals(1, $table[1]['weight']);
  }

  /**
   * Tests the swapColumns() method.
   *
   * @covers ::swapColumns
   */
  public function testSwapColumns(): void {
    $table = [
      0 => [0 => 'A', 1 => 'B'],
      1 => [0 => 'C', 1 => 'D'],
    ];

    ChartDataCollectorTable::swapColumns($table, 0, 1);

    $this->assertEquals('B', $table[0][0]);
    $this->assertEquals('A', $table[0][1]);
    $this->assertEquals('D', $table[1][0]);
    $this->assertEquals('C', $table[1][1]);
  }

}
