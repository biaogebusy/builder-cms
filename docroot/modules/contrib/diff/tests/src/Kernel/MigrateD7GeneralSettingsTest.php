<?php

declare(strict_types=1);

namespace Drupal\Tests\diff\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Test migrating general settings from D7 to config.
 *
 * @group diff
 */
class MigrateD7GeneralSettingsTest extends MigrateDrupal7TestBase {

  /**
   * The migration this test is testing.
   *
   * @var string
   */
  const MIGRATION_UNDER_TEST = 'd7_diff_general_settings';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['diff'];

  /**
   * Test that variables are successfully migrated to configuration.
   */
  public function testMigration(): void {
    Database::getConnection('default', 'migrate')
      ->upsert('system')
      ->key('name')
      ->fields(['name', 'status', 'type'])
      ->values([
        'name' => 'diff',
        'status' => 1,
        'type' => 'module',
      ])
      ->execute();

    // Set up fixtures in the source database.
    $fixtureContentLinesLeading = \random_int(0, 10);
    $this->setUpD7Variable('diff_context_lines_leading', $fixtureContentLinesLeading);
    $fixtureContentLinesTrailing = \random_int(0, 10);
    $this->setUpD7Variable('diff_context_lines_trailing', $fixtureContentLinesTrailing);
    $fixtureRadioBehavior = $this->randomString();
    $this->setUpD7Variable('diff_radio_behavior', $fixtureRadioBehavior);

    // Run the migration.
    $this->executeMigrations([self::MIGRATION_UNDER_TEST]);

    // Verify the variables with migrations are now present in the destination
    // site.
    $config = $this->config('diff.settings');
    $this->assertSame($fixtureContentLinesLeading, $config->get('general_settings.context_lines_leading'));
    $this->assertSame($fixtureContentLinesTrailing, $config->get('general_settings.context_lines_trailing'));
    $this->assertSame($fixtureRadioBehavior, $config->get('general_settings.radio_behavior'));

    // Verify the settings with no source-site equivalent are set to their
    // default values in the destination site.
    $this->assertTrue($config->get('general_settings.layout_plugins.split_fields.enabled'));
    $this->assertSame(1, $config->get('general_settings.layout_plugins.split_fields.weight'));
    $this->assertTrue($config->get('general_settings.layout_plugins.unified_fields.enabled'));
    $this->assertSame(2, $config->get('general_settings.layout_plugins.unified_fields.weight'));
    $this->assertTrue($config->get('general_settings.layout_plugins.visual_inline.enabled'));
    $this->assertSame(0, $config->get('general_settings.layout_plugins.visual_inline.weight'));
    $this->assertSame(50, $config->get('general_settings.revision_pager_limit'));
    $this->assertSame('default', $config->get('general_settings.visual_inline_theme'));
  }

  /**
   * Set up a D7 variable to be migrated.
   *
   * @param string $name
   *   The name of the variable to be set.
   * @param mixed $value
   *   The value of the variable to be set.
   */
  protected function setUpD7Variable(string $name, mixed $value): void {
    Database::getConnection('default', 'migrate')
      ->upsert('variable')
      ->key('name')
      ->fields(['name', 'value'])
      ->values([
        'name' => $name,
        'value' => \serialize($value),
      ])
      ->execute();
  }

}
