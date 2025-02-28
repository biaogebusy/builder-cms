<?php

namespace Drupal\Tests\field_permissions\Kernel\migrate;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests Field Permissions migration.
 *
 * @group field_permissions.
 */
class FieldPermissionsMigrationTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_permissions',
    'node',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getFixtureFilePath() {
    return implode(DIRECTORY_SEPARATOR, [
      \Drupal::service('extension.list.module')->getPath('field_permissions'),
      'tests',
      'fixtures',
      'drupal7.php',
    ]);
  }

  /**
   * Tests field_permissions migration.
   */
  public function testFieldPermissionsMigration() {
    $this->executeMigrations(['d7_field']);
    $storage_after_permission_set = FieldStorageConfig::loadByName('node', 'body');
    $this->assertSame([
      'field_permissions' => [
        'permission_type' => 'custom',
      ],
    ], $storage_after_permission_set->get('third_party_settings'));
    // When field_permissions is not set.
    $storage_after_permission_not_set = FieldStorageConfig::loadByName('node', 'body1');
    $this->assertSame([
      'field_permissions' => [
        'permission_type' => 'public',
      ],
    ], $storage_after_permission_not_set->get('third_party_settings'));
  }

}
