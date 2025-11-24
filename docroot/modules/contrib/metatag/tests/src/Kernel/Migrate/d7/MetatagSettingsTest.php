<?php

namespace Drupal\Tests\metatag\Kernel\Migrate\d7;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Tests migration of misc variables from Metatag-D7.
 *
 * @group metatag
 */
class MetatagSettingsTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Contrib modules.
    'token',

    // This module.
    'metatag',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->loadFixture(__DIR__ . '/../../../../fixtures/d7_metatag.php');

    // Runs the Metatag-D7 settings migration.
    $this->executeMigrations(['d7_metatag_settings']);
  }

  /**
   * Test Metatag settings migration from Drupal 7 to 8.
   */
  public function testSettings() {
    // Load the Metatag config object.
    $config = \Drupal::config('metatag.settings');

    // Compare the settings in the config object with what was migrated.
    $this->assertSame($config->get('separator'), '||');
    $this->assertSame($config->get('use_maxlength'), FALSE);

    // Compare each of the maxlength trim options.
    $trims = $config->get('tag_trim_maxlength');
    $this->assertSame($trims['title'], 50);
    $this->assertSame($trims['description'], 200);
    $this->assertSame($trims['abstract'], 150);
    $this->assertSame($trims['keywords'], 1000);
  }

}
