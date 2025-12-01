<?php

namespace Drupal\Tests\FloodControl\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the flood settings when log in attempts fail.
 *
 * @group flood_control
 */
class FloodControlLoginTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['flood_control'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * User with the permission to access the flood unblock settings.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * A regular user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $webUser;

  /**
   * The module installer.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->moduleInstaller = $this->container->get('module_installer');

    // Defines a user that can configure the module.
    $this->adminUser = $this->createUser(['administer site configuration']);

    // Defines a user that can unblock.
    $this->unblockUser = $this->drupalCreateUser(['access flood unblock']);
  }

  /**
   * Test blockin ip addresses after multiple login attempts.
   */
  public function testIpLoginLimit() {
    $database = \Drupal::database();

    // Removes all flood entries for a fresh start.
    if ($database->schema()->tableExists('flood')) {
      $database->truncate('flood')->execute();
    }

    // Sets the maximum number of ip address login attempts to 3.
    $this->config('user.flood')
      ->set('ip_limit', 3)
      ->save();

    // Attempts 3 logins with the wrong credentials.
    for ($i = 0; $i < 3; $i++) {
      $this->drupalGet('/user/login');
      $this->submitForm([
        'name' => 'wrong-name',
        'pass' => 'wrong-pass',
      ], 'Log in');
      $this->assertSession()
        ->pageTextContains('Unrecognized username or password');
    }

    // Attempts 4th login with wrong credentials to check if ip address is
    // added to the flood table.
    $this->drupalGet('/user/login');
    $this->submitForm([
      'name' => 'wrong-name',
      'pass' => 'wrong-pass',
    ], 'Log in');
    $this->assertSession()->pageTextNotContains('Unrecognized username or password');
    $this->assertSession()
      ->pageTextContains('Login failed');
    $this->assertSession()
      ->pageTextContains('Too many failed login attempts from your IP address');

    // Checks if IP address has been added to the flood table.
    $blocked_addresses = $database->select('flood', 'f')
      ->fields('f', ['fid'])
      ->condition('identifier', '127.0.0.1')
      ->condition('event', 'user.failed_login_ip')
      ->execute()
      ->fetchAll();
    $this->assertGreaterThanOrEqual(1, count($blocked_addresses), 'Blocked IP address not found in flood table');
  }

}
