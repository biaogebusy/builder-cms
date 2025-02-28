<?php

namespace Drupal\Tests\entity_print\Kernel;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Migrates entity print configuration.
 *
 * @group entity_print
 */
class MigrateEntityPrintTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_print',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->loadFixture(implode(DIRECTORY_SEPARATOR, [
      DRUPAL_ROOT,
      \Drupal::service('extension.list.module')->getPath('entity_print'),
      'tests',
      'fixtures',
      'drupal7.php',
    ]));
    $this->installConfig(['entity_print']);
  }

  /**
   * Tests the migration.
   */
  public function testMigration(): void {
    $config_before = $this->config('entity_print.settings');
    $this->assertSame(TRUE, $config_before->get('default_css'));

    $this->executeMigration('entity_print_settings');

    $config_after = $this->config('entity_print.settings');
    $this->assertSame(FALSE, $config_after->get('default_css'));
  }

}
