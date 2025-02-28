<?php

namespace Drupal\Tests\range\Kernel\Migrate\d6;

use Drupal\node\Entity\Node;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\Tests\migrate_drupal\Kernel\d6\MigrateDrupal6TestBase;

/**
 * Tests Drupal 6 range fields migration.
 *
 * @group range
 */
class MigrateRangeFieldTest extends MigrateDrupal6TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'range',
    'text',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->migrateUsers(FALSE);
    $this->migrateContentTypes();
    $this->executeMigrations([
      'd6_field',
      'd6_field_instance',
      'd6_field_instance_widget_settings',
      'd6_view_modes',
      'd6_field_formatter_settings',
      'd6_node_settings',
      'd6_node',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getFixtureFilePath() {
    return __DIR__ . '/../../../../fixtures/drupal6.php';
  }

  /**
   * Tests migration of D6 range fields to field_storage_config entities.
   *
   * @dataProvider fieldMigrationDataProvider
   */
  public function testFieldMigration($id, $type, $settings) {
    /** @var \Drupal\field\FieldStorageConfigInterface $field */
    $field = FieldStorageConfig::load($id);
    $this->assertInstanceOf(FieldStorageConfigInterface::class, $field);
    $this->assertSame($type, $field->getType());
    $this->assertSame($settings, $field->getSettings());
  }

  /**
   * Data provider for testFieldMigration.
   */
  public function fieldMigrationDataProvider() {
    return [
      'range_decimal' => [
        'node.field_decimal',
        'range_decimal',
        [
          'precision' => 12,
          'scale' => 1,
        ],
      ],
      'range_float' => [
        'node.field_float',
        'range_float',
        [],
      ],
      'range_integer' => [
        'node.field_integer',
        'range_integer',
        [],
      ],
    ];
  }

  /**
   * Tests migration of D6 range instances to field_config entities.
   *
   * @dataProvider fieldInstanceMigrationDataProvider
   */
  public function testFieldInstanceMigration($id, $default_value, $settings) {
    /** @var \Drupal\Core\Field\FieldConfigInterface $field */
    $field = FieldConfig::load($id);
    $this->assertSame($default_value, $field->getDefaultValueLiteral());
    $this->assertSame($settings, $field->getSettings());
  }

  /**
   * Data provider for testFieldInstanceMigration.
   */
  public function fieldInstanceMigrationDataProvider() {
    return [
      'range_decimal' => [
        'node.page.field_decimal',
        [
          ['from' => 15.0, 'to' => 15.0],
        ],
        [
          'min' => 5.0,
          'max' => 50.0,
          'field' => ['prefix' => '', 'suffix' => ''],
          'from' => ['prefix' => '', 'suffix' => ''],
          'to' => ['prefix' => '', 'suffix' => ''],
          'combined' => ['prefix' => '', 'suffix' => ''],
          // FieldConfigBase::getSettings() is merging field settings with
          // field storage settings; so let's add them here.
          // @see \Drupal\Core\Field\FieldConfigBase::getSettings()
          'precision' => 12,
          'scale' => 1,
        ],
      ],
      'range_float' => [
        'node.page.field_float',
        [],
        [
          'min' => NULL,
          'max' => 1000.0,
          'field' => ['prefix' => '', 'suffix' => 'SUFFIX'],
          'from' => ['prefix' => '', 'suffix' => ''],
          'to' => ['prefix' => '', 'suffix' => ''],
          'combined' => ['prefix' => '', 'suffix' => ''],
        ],
      ],
      'range_integer' => [
        'node.page.field_integer',
        [],
        [
          'min' => NULL,
          'max' => NULL,
          'field' => ['prefix' => '', 'suffix' => ''],
          'from' => ['prefix' => '', 'suffix' => ''],
          'to' => ['prefix' => '', 'suffix' => ''],
          'combined' => ['prefix' => '', 'suffix' => ''],
        ],
      ],
    ];
  }

  /**
   * Tests migration of D6 range field widget and its settings.
   *
   * @dataProvider fieldWidgetMigrationDataProvider
   */
  public function testFieldWidgetMigration($display_id, $component_id, $type, $settings) {
    $component = EntityFormDisplay::load($display_id)->getComponent($component_id);
    $this->assertSame($type, $component['type']);
    $this->assertSame($settings, $component['settings']);
  }

  /**
   * Data provider for testFieldWidgetMigration.
   */
  public function fieldWidgetMigrationDataProvider() {
    return [
      'range_decimal' => [
        'node.page.default',
        'field_decimal',
        'range',
        [
          'label' => ['from' => 'From', 'to' => 'to'],
          'placeholder' => ['from' => '', 'to' => ''],
        ],
      ],
      'range_float' => [
        'node.page.default',
        'field_float',
        'range',
        [
          'label' => ['from' => 'From', 'to' => 'to'],
          'placeholder' => ['from' => '', 'to' => ''],
        ],
      ],
      'range_integer' => [
        'node.page.default',
        'field_integer',
        'range',
        [
          'label' => ['from' => 'From', 'to' => 'to'],
          'placeholder' => ['from' => '', 'to' => ''],
        ],
      ],
    ];
  }

  /**
   * Tests migration of D6 range field formatters & their settings.
   *
   * @dataProvider fieldFormatterMigrationDataProvider
   */
  public function testFieldFormatterMigration($display_id, $component_id, $type, $settings) {
    $component = EntityViewDisplay::load($display_id)->getComponent($component_id);
    $this->assertIsArray($component);
    $this->assertSame($type, $component['type']);
    $this->assertSame($settings, $component['settings']);
  }

  /**
   * Data provider for testFieldFormatterMigration.
   */
  public function fieldFormatterMigrationDataProvider() {
    return [
      // Range decimal field type.
      'unformatted range_decimal' => [
        'node.page.default',
        'field_decimal',
        'range_unformatted',
        [
          'range_separator' => '-',
          'range_combine' => TRUE,
          'field_prefix_suffix' => TRUE,
          'from_prefix_suffix' => FALSE,
          'to_prefix_suffix' => FALSE,
          'combined_prefix_suffix' => FALSE,
        ],
      ],
      'default range_decimal' => [
        'node.page.teaser',
        'field_decimal',
        'range_decimal',
        [
          'range_separator' => '-',
          'thousand_separator' => '',
          'decimal_separator' => '.',
          'scale' => 2,
          'range_combine' => TRUE,
          'field_prefix_suffix' => TRUE,
          'from_prefix_suffix' => FALSE,
          'to_prefix_suffix' => FALSE,
          'combined_prefix_suffix' => FALSE,
        ],
      ],
      'us_2 range_decimal' => [
        'node.page.rss',
        'field_decimal',
        'range_decimal',
        [
          'range_separator' => '-',
          'thousand_separator' => ',',
          'decimal_separator' => '.',
          'scale' => 2,
          'range_combine' => TRUE,
          'field_prefix_suffix' => TRUE,
          'from_prefix_suffix' => FALSE,
          'to_prefix_suffix' => FALSE,
          'combined_prefix_suffix' => FALSE,
        ],
      ],
      // Range float field type.
      'unformatted range_float' => [
        'node.page.default',
        'field_float',
        'range_unformatted',
        [
          'range_separator' => '-',
          'range_combine' => TRUE,
          'field_prefix_suffix' => TRUE,
          'from_prefix_suffix' => FALSE,
          'to_prefix_suffix' => FALSE,
          'combined_prefix_suffix' => FALSE,
        ],
      ],
      'default range_float' => [
        'node.page.teaser',
        'field_float',
        'range_decimal',
        [
          'range_separator' => '-',
          'thousand_separator' => '',
          'decimal_separator' => '.',
          'scale' => 2,
          'range_combine' => TRUE,
          'field_prefix_suffix' => TRUE,
          'from_prefix_suffix' => FALSE,
          'to_prefix_suffix' => FALSE,
          'combined_prefix_suffix' => FALSE,
        ],
      ],
      'be_1 range_float' => [
        'node.page.rss',
        'field_float',
        'range_decimal',
        [
          'range_separator' => '-',
          'thousand_separator' => '.',
          'decimal_separator' => ',',
          'scale' => 1,
          'range_combine' => TRUE,
          'field_prefix_suffix' => TRUE,
          'from_prefix_suffix' => FALSE,
          'to_prefix_suffix' => FALSE,
          'combined_prefix_suffix' => FALSE,
        ],
      ],
      // Range integer field type.
      'unformatted range_integer' => [
        'node.page.default',
        'field_integer',
        'range_unformatted',
        [
          'range_separator' => '-',
          'range_combine' => TRUE,
          'field_prefix_suffix' => TRUE,
          'from_prefix_suffix' => FALSE,
          'to_prefix_suffix' => FALSE,
          'combined_prefix_suffix' => FALSE,
        ],
      ],
      'default range_integer' => [
        'node.page.teaser',
        'field_integer',
        'range_integer',
        [
          'range_separator' => '-',
          'thousand_separator' => '',
          'range_combine' => TRUE,
          'field_prefix_suffix' => TRUE,
          'from_prefix_suffix' => FALSE,
          'to_prefix_suffix' => FALSE,
          'combined_prefix_suffix' => FALSE,
        ],
      ],
      'fr_0 range_integer' => [
        'node.page.rss',
        'field_integer',
        'range_integer',
        [
          'range_separator' => '-',
          'thousand_separator' => ' ',
          'range_combine' => TRUE,
          'field_prefix_suffix' => TRUE,
          'from_prefix_suffix' => FALSE,
          'to_prefix_suffix' => FALSE,
          'combined_prefix_suffix' => FALSE,
        ],
      ],
    ];
  }

  /**
   * Tests migration of D6 range fields data.
   *
   * @dataProvider fieldDataMigrationDataProvider
   */
  public function testFieldDataMigration($field_name, $data) {

    $node = Node::load(1);
    foreach ($data as $i => $expected) {
      // Normalize data presentation, as this test is about data value, and is
      // not about amount of zeros in the end (there is a difference between
      // database drivers).
      $format = $field_name === 'field_integer' ? '%d' : '%.1f';
      $this->assertSame($expected['from'], sprintf($format, $node->{$field_name}->get($i)->from));
      $this->assertSame($expected['to'], sprintf($format, $node->{$field_name}->get($i)->to));
    }
  }

  /**
   * Data provider for testFieldDataMigration.
   */
  public function fieldDataMigrationDataProvider() {
    return [
      'range_decimal' => [
        'field_decimal',
        [
          [
            'from' => '55.6',
            'to' => '55.6',
          ],
          [
            'from' => '67.7',
            'to' => '67.7',
          ],
          [
            'from' => '88.9',
            'to' => '88.9',
          ],
        ],
      ],
      'range_float' => [
        'field_float',
        [],
      ],
      'range_integer' => [
        'field_integer',
        [
          [
            'from' => '120',
            'to' => '120',
          ],
        ],
      ],
    ];
  }

}
