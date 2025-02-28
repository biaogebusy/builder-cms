<?php

namespace Drupal\Tests\range\Unit\Plugin\migrate\field\d7;

use Drupal\migrate\Plugin\MigrateSourceInterface;
use Drupal\migrate\Plugin\Migration;
use Drupal\migrate\Row;
use Drupal\range\Plugin\migrate\field\d7\RangeField;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\range\Plugin\migrate\field\d7\RangeField
 * @group range
 */
class RangeFieldTest extends UnitTestCase {

  protected const PLUGIN_DEFINITION = [
    'id' => 'd7_range',
    'core' => [7],
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
   * @var \Drupal\range\Plugin\migrate\field\d7\RangeField
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->row = $this->createMock(Row::class);

    $this->migrationSource = $this->createMock(MigrateSourceInterface::class);
    $this->migrationSource->expects(self::once())
      ->method('current')
      ->willReturn($this->row);

    $this->migration = $this->createPartialMock(Migration::class, ['getSourcePlugin']);
    $this->migration->expects(self::once())
      ->method('getSourcePlugin')
      ->willReturn($this->migrationSource);

    $this->plugin = new RangeField([], 'range', self::PLUGIN_DEFINITION);
  }

  /**
   * @covers ::alterFieldWidgetMigration
   * @dataProvider alterMigrationDataProvider
   */
  public function testAlterFieldWidgetMigration($field_type, $is_range_field_type) {

    $this->row->expects(self::once())
      ->method('getSourceProperty')
      ->willReturn($field_type);

    $this->plugin->alterFieldWidgetMigration($this->migration);
    $process = $this->migration->getProcess();

    if ($is_range_field_type) {
      $this->assertSame(['range' => 'range'], $process['options/type']['type']['map']);
      $expected_process = [
        'plugin' => 'd7_range_field_instance_widget_settings',
        'source' => 'settings',
      ];
      $this->assertSame([$expected_process], $process['options/settings']);
    }
    else {
      $this->assertArrayNotHasKey('options/settings', $process);
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
      $expected_process = [
        'plugin' => 'd7_range_field_instance_settings',
      ];
      $this->assertSame([$expected_process], $process['settings']);
    }
    else {
      $this->assertSame([], $process);
    }
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
