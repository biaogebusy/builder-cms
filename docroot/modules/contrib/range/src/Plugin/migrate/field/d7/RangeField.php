<?php

namespace Drupal\range\Plugin\migrate\field\d7;

use Drupal\migrate_drupal\Plugin\migrate\field\FieldPluginBase;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * MigrateField Plugin for Drupal 7 range fields.
 *
 * @MigrateField(
 *   id = "d7_range",
 *   core = {7},
 *   type_map = {
 *     "range_integer" = "range_integer",
 *     "range_decimal" = "range_decimal",
 *     "range_float" = "range_float"
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
  public function alterFieldInstanceMigration(MigrationInterface $migration) {
    /** @var \Drupal\migrate\Row $row */
    $row = $migration->getSourcePlugin()->current();
    if ($this->isRangeField($row->getSourceProperty('type'))) {
      $process = [
        'plugin' => 'd7_range_field_instance_settings',
      ];

      $migration->mergeProcessOfProperty('settings', $process);
    }

    parent::alterFieldInstanceMigration($migration);
  }

  /**
   * {@inheritdoc}
   */
  public function alterFieldWidgetMigration(MigrationInterface $migration) {
    /** @var \Drupal\migrate\Row $row */
    $row = $migration->getSourcePlugin()->current();
    if ($this->isRangeField($row->getSourceProperty('type'))) {
      $process = [
        'plugin' => 'd7_range_field_instance_widget_settings',
        // Range widget settings are stored in the instance settings in D7.
        'source' => 'settings',
      ];

      $migration->mergeProcessOfProperty('options/settings', $process);
    }

    parent::alterFieldWidgetMigration($migration);
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
