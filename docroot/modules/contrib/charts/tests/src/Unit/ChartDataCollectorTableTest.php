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
    $this->assertCount(13, $info);
    $this->assertArrayHasKey('#theme_wrappers', $info);
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
   * Tests processDataCollectorTable() when .
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

}
