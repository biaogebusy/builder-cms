<?php

namespace Drupal\Tests\captcha\Kernel\Migrate\d7;

use Drupal\Tests\migrate_drupal\Kernel\d7\MigrateDrupal7TestBase;
use Drupal\captcha\Constants\CaptchaConstants;

/**
 * Migrates various configuration objects owned by the captcha module.
 *
 * @group captcha
 */
class MigrateCaptchaSimpleConfigurationTest extends MigrateDrupal7TestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['captcha'];

  /**
   * The expected configuration from the Captcha fixture.
   *
   * @var array[]
   */
  protected $expectedConfig = [
    'captcha.settings' => [
      'enable_globally' => 1,
      'enable_globally_on_admin_routes' => FALSE,
      'default_challenge' => CaptchaConstants::CAPTCHA_MATH_CAPTCHA_TYPE,
      'description' => 'This question is for testing whether or not you are a human visitor and to prevent automated spam submissions.',
      'administration_mode' => TRUE,
      'administration_mode_on_admin_routes' => FALSE,
      'default_validation' => 1,
      'persistence' => 1,
      'enable_stats' => TRUE,
      'log_wrong_responses' => TRUE,
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->loadFixture(implode(DIRECTORY_SEPARATOR, [
      DRUPAL_ROOT,
      \Drupal::service('extension.list.module')->getPath('captcha'),
      'tests',
      'fixtures',
      'drupal7.php',
    ]));

    $migrations = [
      'd7_captcha_settings',
    ];
    $this->executeMigrations($migrations);
  }

  /**
   * Tests that all expected configuration gets migrated.
   */
  public function testConfigurationMigration() {
    // Test Config.
    $this->expectedConfig['captcha.settings'] = version_compare(\Drupal::VERSION, '10.3.0', '>=') ? ['langcode' => 'en'] + $this->expectedConfig['captcha.settings'] : $this->expectedConfig['captcha.settings'];
    foreach ($this->expectedConfig as $config_id => $values) {
      $actual = \Drupal::config($config_id)->get();
      $this->assertSame($values, $actual);
    }
  }

}
