<?php

namespace Drupal\Tests\flood_control\Kernel;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests flood_control configuration.
 *
 * @group flood_control
 */
class MigrateFloodControlTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'flood_control',
    'contact',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getFixtureFilePath() {
    return implode(DIRECTORY_SEPARATOR, [
      \Drupal::service('extension.list.module')->getPath('flood_control'),
      'tests',
      'fixtures',
      'drupal7.php',
    ]);
  }

  /**
   * Tests flood_control config migration.
   */
  public function testMigration(): void {
    $this->installConfig(['contact']);
    $this->executeMigration('contact_category');
    $this->executeMigration('d7_contact_settings');
    $config_after = $this->config('contact.settings');
    $flood_limit = $config_after->get('flood.limit');
    $flood_interval = $config_after->get('flood.interval');
    $this->assertEquals(20, $flood_limit);
    $this->assertEquals(900, $flood_interval);
  }

}
