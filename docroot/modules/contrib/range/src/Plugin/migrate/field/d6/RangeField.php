<?php

namespace Drupal\range\Plugin\migrate\field\d6;

use Drupal\migrate_drupal\Plugin\migrate\field\FieldPluginBase;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * MigrateField Plugin for Drupal 6 range fields.
 *
 * @MigrateField(
 *   id = "d6_range",
 *   core = {6},
 *   type_map = {
 *     "range_decimal" = "range_decimal",
 *     "range_float" = "range_float",
 *     "range_integer" = "range_integer",
 *   },
 *   source_module = "range",
 *   destination_module = "range"
 * )
 */
class RangeField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetMap() {
    return [
      'range' => 'range',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function alterFieldMigration(MigrationInterface $migration) {
    /** @var \Drupal\migrate\Row $row */
    $row = $migration->getSourcePlugin()->current();
    if ($this->isRangeField($row->getSourceProperty('type'))) {
      $process_type = [];
      $process_type[0]['map'] = [
        'range_decimal' => [
          'range' => 'range_decimal',
        ],
        'range_float' => [
          'range' => 'range_float',
        ],
        'range_integer' => [
          'range' => 'range_integer',
        ],
      ];
      $migration->mergeProcessOfProperty('type', $process_type);

      $process_settings = [
        'plugin' => 'd6_range_field_settings',
      ];
      $migration->mergeProcessOfProperty('settings', $process_settings);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterFieldInstanceMigration(MigrationInterface $migration) {
    /** @var \Drupal\migrate\Row $row */
    $row = $migration->getSourcePlugin()->current();
    if ($this->isRangeField($row->getSourceProperty('type'))) {
      $process_settings = [
        'plugin' => 'd6_range_field_instance_settings',
      ];
      $migration->mergeProcessOfProperty('settings', $process_settings);

      $process_defaults = [
        'plugin' => 'd6_range_field_instance_defaults',
      ];
      $migration->mergeProcessOfProperty('default_value', $process_defaults);
    }

    parent::alterFieldInstanceMigration($migration);
  }

  /**
   * {@inheritdoc}
   */
  public function alterFieldFormatterMigration(MigrationInterface $migration) {
    /** @var \Drupal\migrate\Row $row */
    $row = $migration->getSourcePlugin()->current();
    if ($this->isRangeField($row->getSourceProperty('type'))) {
      $process_type = [];
      $process_type[0]['map']['range_decimal'] = $process_type[0]['map']['range_float'] = [
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
      ];
      $process_type[0]['map']['range_integer'] = [
        'default' => 'range_integer',
        'us_0' => 'range_integer',
        'be_0' => 'range_integer',
        'fr_0' => 'range_integer',
        'unformatted' => 'range_unformatted',
      ];
      $migration->mergeProcessOfProperty('options/type', $process_type);

      $process_settings = [
        'plugin' => 'd6_range_field_formatter_settings',
      ];
      $migration->mergeProcessOfProperty('options/settings', $process_settings);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defineValueProcessPipeline(MigrationInterface $migration, $field_name, $data) {
    $process = [
      'plugin' => 'd6_range_field',
      'source' => $field_name,
    ];
    $migration->mergeProcessOfProperty($field_name, $process);
  }

  /**
   * Checks whether the given field type is a range field.
   *
   * This plugin is being called for every single field type existing in the
   * source database.
   *
   * @param string $type
   *   Field type that is being migrated.
   *
   * @return bool
   *   TRUE if the given field type is a range field, FALSE otherwise.
   */
  private function isRangeField($type) {
    return array_key_exists($type, $this->pluginDefinition['type_map']);
  }

}
