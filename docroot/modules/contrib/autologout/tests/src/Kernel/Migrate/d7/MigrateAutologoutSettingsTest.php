<?php

namespace Drupal\Tests\autologout\Kernel\Migrate\d7;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;

/**
 * Upgrade variables to autologout.settings.yml.
 *
 * @group Autologout
 */
class MigrateAutologoutSettingsTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'autologout',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->loadFixture(implode(DIRECTORY_SEPARATOR, [
      DRUPAL_ROOT,
      \Drupal::service('extension.list.module')->getPath('autologout'),
      'tests',
      'fixtures',
      'drupal7.php',
    ]));
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
    $this->assertFalse($config->get('role_logout'));
    $this->assertSame('user/login', $config->get('redirect_url'));
    $this->assertFalse($config->get('no_dialog'));
    $this->assertSame('We are about to log you out for inactivity. If we do, you will lose any unsaved work. Do you need more time?', $config->get('message'));
    $this->assertSame('You have been logged out due to inactivity.', $config->get('inactivity_message'));
    $this->assertFalse($config->get('enforce_admin'));
    $this->assertFalse($config->get('use_alt_logout_method'));
    $this->assertFalse($config->get('use_watchdog'));
    $this->assertSame("0", $config->get('whitelisted_ip_addresses'));
  }

}
