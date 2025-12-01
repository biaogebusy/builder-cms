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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->database = $this->container->get('database');
  }

  /**
   * Test blocking ip addresses after multiple login attempts.
   */
  public function testIpLoginLimit() {

    // Removes all flood entries for a fresh start.
    if ($this->database->schema()->tableExists('flood')) {
      $this->database->truncate('flood')->execute();
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
    $this->assertSession()
      ->pageTextNotContains('Unrecognized username or password');
    $this->assertSession()
      ->pageTextContains('Login failed');
    $this->assertSession()
      ->pageTextContains('Too many failed login attempts from your IP address');

    // Check that events have been added to the flood table. It was truncated
    // at the start of this test so any entry present is success. The IP used
    // is not guaranteed to be 127.0.0.1.
    $blocked_addresses = $this->database->select('flood', 'f')
      ->fields('f', ['fid'])
      ->condition('event', 'user.failed_login_ip')
      ->execute()
      ->fetchAll();
    $this->assertGreaterThanOrEqual(
      1,
      count($blocked_addresses),
      'Blocked IP address not found in flood table'
    );
  }

}
