<?php

declare(strict_types=1);

namespace Drupal\Tests\charts_blocks\Kernel\Plugin\Block;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the ChartsCanvasBlock component for Drupal Canvas.
 *
 * @group charts_blocks
 */
final class ChartsCanvasBlockTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'charts',
    'charts_blocks',
  ];

  /**
   * The Block Plugin Manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->blockManager = $this->container->get('plugin.manager.block');
  }

  /**
   * Tests that the block builds correctly with valid JSON data.
   */
  public function testBuildWithValidJson(): void {
    /** @var \Drupal\charts_blocks\Plugin\Block\ChartsCanvasBlock $block */
    $block = $this->blockManager->createInstance('charts_canvas_block');

    // Set valid JSON configuration.
    $block->setConfigurationValue('data_format', 'json');
    $block->setConfigurationValue('chart_type', 'bar');
    $json_data = '{"categories":["Q1","Q2"],"series":[{"name":"Product A","data":[120,145],"color":"#1f77b4"}]}';
    $block->setConfigurationValue('data', $json_data);

    $build = $block->build();

    $this->assertEquals('chart', $build['#type']);
    $this->assertEquals('bar', $build['#chart_type']);
    $this->assertEquals(['Q1', 'Q2'], $build['xaxis']['#labels']);
    $this->assertEquals('Product A', $build['series_0']['#title']);
    $this->assertEquals([120, 145], $build['series_0']['#data']);
    $this->assertEquals('#1f77b4', $build['series_0']['#color']);
  }

  /**
   * Tests that the block handles invalid JSON safely during live editing.
   */
  public function testInvalidJsonValidation(): void {
    $block = $this->blockManager->createInstance('charts_canvas_block');
    $form_state = new FormState();

    // Provide a broken JSON string (simulating mid-typing in Canvas).
    $form_state->setValues([
      'data_format' => 'json',
      'data' => '{"categories":["Q1","Q2"],"series":[{',
    ]);

    $form = [];
    $block->blockValidate($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('data', $errors, 'Validation should fail for malformed JSON.');
    $this->assertEquals('Invalid JSON syntax.', (string) $errors['data']);
  }

  /**
   * Tests security edge cases: Malicious JSON and XSS payloads.
   */
  public function testSecurityAndDangerousJson(): void {
    /** @var \Drupal\charts_blocks\Plugin\Block\ChartsCanvasBlock $block */
    $block = $this->blockManager->createInstance('charts_canvas_block');

    // Test XSS payloads.
    // Ensure malicious scripts in categories or series names don't crash the
    // parser and are passed strictly as data to the render array (where Twig
    // will safely auto-escape them during HTML rendering).
    $xss_json = json_encode([
      'categories' => ['<script>alert("xss")</script>'],
      'series' => [
        [
          'name' => '<img src=x onerror=alert(1)>',
          'data' => [100],
        ],
      ],
    ]);

    $block->setConfigurationValue('data_format', 'json');
    $block->setConfigurationValue('data', $xss_json);

    $build = $block->build();

    $this->assertEquals(['<script>alert("xss")</script>'], $build['xaxis']['#labels']);
    $this->assertEquals('<img src=x onerror=alert(1)>', $build['series_0']['#title']);

    // Test JSON bomb (deep recursion DoS attempt).
    // A maliciously deep JSON string can exhaust PHP memory or crash the JSON
    // decoder. We verify the try/catch intercepts the decoding exception.
    $form_state = new FormState();

    // Create an extremely deeply nested array.
    $deep_json = '{"categories":' . str_repeat('{"a":', 600) . '"bomb"' . str_repeat('}', 600) . '}';

    $form_state->setValues([
      'data_format' => 'json',
      'data' => $deep_json,
    ]);

    $form = [];
    $block->blockValidate($form, $form_state);

    $errors = $form_state->getErrors();
    // The decoder will fail on depth limits, and our block should catch it
    // gracefully without fatal errors for the test or the server.
    $this->assertArrayHasKey('data', $errors);
    $this->assertEquals('Invalid JSON syntax.', (string) $errors['data']);
  }

  /**
   * Tests parsing of CSV data.
   */
  public function testCsvParsing(): void {
    /** @var \Drupal\charts_blocks\Plugin\Block\ChartsCanvasBlock $block */
    $block = $this->blockManager->createInstance('charts_canvas_block');

    $csv_data = "Quarter,Product A,Product B\nQ1,120,90\nQ2,145,110";

    $block->setConfigurationValue('data_format', 'csv');
    $block->setConfigurationValue('data', $csv_data);
    $block->setConfigurationValue('chart_type', 'column');

    $build = $block->build();

    $this->assertEquals(['Q1', 'Q2'], $build['xaxis']['#labels']);
    $this->assertEquals('Product A', $build['series_0']['#title']);
    $this->assertEquals([120, 145], $build['series_0']['#data']);
    $this->assertEquals('Product B', $build['series_1']['#title']);
    $this->assertEquals([90, 110], $build['series_1']['#data']);
  }

  /**
   * Tests per-series chart type overrides (combo charts) via JSON.
   */
  public function testBuildWithPerSeriesChartType(): void {
    /** @var \Drupal\charts_blocks\Plugin\Block\ChartsCanvasBlock $block */
    $block = $this->blockManager->createInstance('charts_canvas_block');

    $block->setConfigurationValue('data_format', 'json');
    $block->setConfigurationValue('chart_type', 'column');
    $json_data = json_encode([
      'categories' => [
        'Q1',
        'Q2',
        'Q3',
      ],
      'series' => [
        [
          'name' => 'Revenue',
          'data' => [
            120,
            145,
            160,
          ],
        ],
        [
          'name' => 'Margin %',
          'data' => [
            12,
            14,
            15,
          ],
          'target_axis' => 'secondary_yaxis',
          'chart_type' => 'line',
        ],
      ],
    ]);
    $block->setConfigurationValue('data', $json_data);

    $build = $block->build();

    // The base chart type is unchanged.
    $this->assertEquals('column', $build['#chart_type']);

    // The first series has no override, so it inherits the base type.
    $this->assertArrayNotHasKey('#chart_type', $build['series_0']);

    // The second series overrides the type and targets the secondary axis.
    $this->assertEquals('line', $build['series_1']['#chart_type']);
    $this->assertEquals('secondary_yaxis', $build['series_1']['#target_axis']);
    $this->assertArrayHasKey('secondary_yaxis', $build);
  }

  /**
   * Tests that a per-series chart type is ignored for single-axis charts.
   */
  public function testPerSeriesChartTypeIgnoredForPie(): void {
    /** @var \Drupal\charts_blocks\Plugin\Block\ChartsCanvasBlock $block */
    $block = $this->blockManager->createInstance('charts_canvas_block');

    $block->setConfigurationValue('data_format', 'json');
    $block->setConfigurationValue('chart_type', 'pie');
    $json_data = json_encode([
      'categories' => [
        'A',
        'B',
        'C',
      ],
      'series' => [
        [
          'name' => 'Share',
          'data' => [
            30,
            45,
            25,
          ],
          'chart_type' => 'line',
        ],
      ],
    ]);
    $block->setConfigurationValue('data', $json_data);

    $build = $block->build();

    // Pie uses only the first series and never applies a per-series type.
    $this->assertEquals('pie', $build['#chart_type']);
    $this->assertArrayNotHasKey('#chart_type', $build['series_0']);
  }

}
