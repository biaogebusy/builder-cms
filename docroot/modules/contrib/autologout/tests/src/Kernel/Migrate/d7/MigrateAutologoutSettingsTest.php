<?php

namespace Drupal\Tests\autologout\Kernel\Migrate\d7;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Upgrade variables to autologout.settings.yml.
 *
 * @group migrate_drupal_7
 */
class MigrateAutologoutSettingsTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['autologout'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(static::$modules);
    $this->executeMigrations(['d7_autologout_settings']);
  }

  /**
   * Tests migration of autologout variables to autologout.settings.yml.
   */
  public function testAutologoutSettings() {
    $config = $this->config('autologout.settings');
    $this->assertSame(1800, $config->get('timeout'));
    $this->assertSame(172800, $config->get('max_timeout'));
    $this->assertSame(20, $config->get('padding'));
    $this->assertSame(FALSE, $config->get('role_logout'));
    $this->assertSame('/user/login', $config->get('redirect_url'));
    $this->assertSame(FALSE, $config->get('no_dialog'));
    $this->assertSame('Your session is about to expire. Do you want to reset it?', $config->get('message'));
    $this->assertSame('You have been logged out due to inactivity.', $config->get('inactivity_message'));
    $this->assertSame(FALSE, $config->get('enforce_admin'));
    $this->assertSame(FALSE, $config->get('use_alt_logout_method'));
    $this->assertSame(TRUE, $config->get('use_watchdog'));
  }

}
