<?php

namespace Drupal\Tests\range\Unit\Plugin\migrate\field\d6;

use Drupal\migrate\Plugin\MigrateSourceInterface;
use Drupal\migrate\Plugin\Migration;
use Drupal\migrate\Row;
use Drupal\range\Plugin\migrate\field\d6\RangeField;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\range\Plugin\migrate\field\d6\RangeField
 * @group range
 */
class RangeFieldTest extends UnitTestCase {

  protected const PLUGIN_DEFINITION = [
    'id' => 'd6_range',
    'core' => [6],
    'type_map' => [
      'range_integer' => 'range_integer',
      'range_decimal' => 'range_decimal',
      'range_float' => 'range_float',
    ],
    'source_module' => 'range',
    'destination_module' => 'range',
  ];

  /**
   * Current migration row.
   *
   * @var \Drupal\migrate\Row|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $row;

  /**
   * Migration source.
   *
   * @var \Drupal\migrate\Plugin\MigrateSourceInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $migrationSource;

  /**
   * Migration itself.
   *
   * @var \Drupal\migrate\Plugin\Migration|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $migration;

  /**
   * RangeField migration plugin.
   *
   * @var \Drupal\range\Plugin\migrate\field\d6\RangeField
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->row = $this->createMock(Row::class);

    $this->migrationSource = $this->createMock(MigrateSourceInterface::class);
    $this->migrationSource->expects(self::atMost(1))
      ->method('current')
      ->willReturn($this->row);

    $this->migration = $this->createPartialMock(Migration::class, ['getSourcePlugin']);
    $this->migration->expects(self::atMost(1))
      ->method('getSourcePlugin')
      ->willReturn($this->migrationSource);

    $this->plugin = new RangeField([], 'range', self::PLUGIN_DEFINITION);
  }

  /**
   * @covers ::alterFieldMigration
   * @dataProvider alterMigrationDataProvider
   */
  public function testAlterFieldMigration($field_type, $is_range_field_type) {

    $this->row->expects(self::once())
      ->method('getSourceProperty')
      ->willReturn($field_type);

    $this->plugin->alterFieldMigration($this->migration);
    $process = $this->migration->getProcess();

    if ($is_range_field_type) {
      $this->assertSame([
        'range_decimal' => [
          'range' => 'range_decimal',
        ],
        'range_float' => [
          'range' => 'range_float',
        ],
        'range_integer' => [
          'range' => 'range_integer',
        ],
      ], $process['type'][0]['map']);

      $expected_process = [
        'plugin' => 'd6_range_field_settings',
      ];
      $this->assertSame([$expected_process], $process['settings']);
    }
    else {
      $this->assertSame([], $process);
    }
  }

  /**
   * @covers ::alterFieldInstanceMigration
   * @dataProvider alterMigrationDataProvider
   */
  public function testAlterFieldInstanceMigration($field_type, $is_range_field_type) {

    $this->row->expects(self::once())
      ->method('getSourceProperty')
      ->willReturn($field_type);

    $this->plugin->alterFieldInstanceMigration($this->migration);
    $process = $this->migration->getProcess();

    if ($is_range_field_type) {
      $expected_process_settings = [
        'plugin' => 'd6_range_field_instance_settings',
      ];
      $this->assertSame([$expected_process_settings], $process['settings']);

      $expected_process_defaults = [
        'plugin' => 'd6_range_field_instance_defaults',
      ];
      $this->assertSame([$expected_process_defaults], $process['default_value']);
    }
    else {
      $this->assertSame([], $process);
    }
  }

  /**
   * @covers ::alterFieldFormatterMigration
   * @dataProvider alterMigrationDataProvider
   */
  public function testAlterFieldFormatterMigration($field_type, $is_range_field_type) {

    $this->row->expects(self::once())
      ->method('getSourceProperty')
      ->willReturn($field_type);

    $this->plugin->alterFieldFormatterMigration($this->migration);
    $process = $this->migration->getProcess();

    if ($is_range_field_type) {
      $this->assertSame([
        'default' => 'range_decimal',
        'us_0' => 'range_decimal',
        'us_1' => 'range_decimal',
        'us_2' => 'range_decimal',
        'be_0' => 'range_decimal',
        'be_1' => 'range_decimal',
        'be_2' => 'range_decimal',
        'fr_0' => 'range_decimal',
        'fr_1' => 'range_decimal',
        'fr_2' => 'range_decimal',
        'unformatted' => 'range_unformatted',
      ], $process['options/type'][0]['map']['range_decimal']);
      $this->assertSame([
        'default' => 'range_decimal',
        'us_0' => 'range_decimal',
        'us_1' => 'range_decimal',
        'us_2' => 'range_decimal',
        'be_0' => 'range_decimal',
        'be_1' => 'range_decimal',
        'be_2' => 'range_decimal',
        'fr_0' => 'range_decimal',
        'fr_1' => 'range_decimal',
        'fr_2' => 'range_decimal',
        'unformatted' => 'range_unformatted',
      ], $process['options/type'][0]['map']['range_float']);
      $this->assertSame([
        'default' => 'range_integer',
        'us_0' => 'range_integer',
        'be_0' => 'range_integer',
        'fr_0' => 'range_integer',
        'unformatted' => 'range_unformatted',
      ], $process['options/type'][0]['map']['range_integer']);

      $expected_process = [
        'plugin' => 'd6_range_field_formatter_settings',
      ];
      $this->assertSame([$expected_process], $process['options/settings']);
    }
    else {
      $this->assertSame([], $process);
    }
  }

  /**
   * @covers ::defineValueProcessPipeline
   * @dataProvider alterMigrationDataProvider
   */
  public function testDefineValueProcessPipeline($field_type) {
    $this->plugin->defineValueProcessPipeline($this->migration, $field_type, []);
    $process = $this->migration->getProcess();

    $expected_process = [
      'plugin' => 'd6_range_field',
      'source' => $field_type,
    ];
    $this->assertSame([$expected_process], $process[$field_type]);
  }

  /**
   * Data provider.
   */
  public function alterMigrationDataProvider() {
    return [
      'not range field' => ['link', FALSE],
      'range integer field' => ['range_integer', TRUE],
      'range decimal field' => ['range_decimal', TRUE],
      'range float field' => ['range_float', TRUE],
    ];
  }

}
