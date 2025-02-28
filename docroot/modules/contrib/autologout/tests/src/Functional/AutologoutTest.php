<?php

namespace Drupal\Tests\autologout\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the autologout's features.
 *
 * @description Ensures that the autologout module functions as expected
 *
 * @group Autologout
 */
class AutologoutTest extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'node',
    'system',
    'user',
    'views',
    'autologout',
    'block',
  ];

  /**
   * User with admin rights.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $privilegedUser;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Stores the user data service used by the test.
   *
   * @var \Drupal\user\UserDataInterface
   */
  public $userData;

  /**
   * Performs any pre-requisite tasks that need to happen.
   */
  public function setUp(): void {
    parent::setUp();
    // Create and log in our privileged user.
    $this->privilegedUser = $this->drupalCreateUser([
      'access content',
      'administer site configuration',
      'access site reports',
      'access administration pages',
      'bypass node access',
      'administer content types',
      'administer nodes',
      'administer autologout',
      'change own logout threshold',
      'access site reports',
      'view the administration theme',
    ]);

    $this->configFactory = $this->container->get('config.factory');
    $this->userData = $this->container->get('user.data');

    // For the purposes of the test, set the timeout periods to 10 seconds.
    $this->configFactory->getEditable('autologout.settings')
      ->set('timeout', 10)
      ->set('padding', 0)
      ->save();
    // Make node page default.
    $this->configFactory->getEditable('system.site')
      ->set('page.front', 'node')
      ->save();

    $this->drupalLogin($this->privilegedUser);
  }

  /**
   * Tests a user is logged out after the default timeout period.
   */
  public function testAutologoutDefaultTimeout() {
    // Check that the user can access the page after login.
    $this->drupalGet('node');
    $this->assertSession()->statusCodeEquals(200);
    self::assertTrue($this->drupalUserIsLoggedIn($this->privilegedUser));

    // Wait for timeout period to elapse.
    sleep(15);

    // Check we are now logged out.
    $this->drupalGet('node');
    $this->assertSession()->statusCodeEquals(200);
    self::assertFalse($this->drupalUserIsLoggedIn($this->privilegedUser));
  }

  /**
   * Tests a user is logged out with the alternate logout method.
   */
  public function testAutologoutAlternateLogoutMethod() {
    // Test that alternate logout works as expected.
    $this->drupalGet('autologout_alt_logout');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains(
      $this->t('You have been logged out due to inactivity.')
    );

    // Check further logout requests result in access denied.
    $this->drupalGet('autologout_alt_logout');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests a user is not logged out within the default timeout period.
   */
  public function testAutologoutNoLogoutInsideTimeout() {
    // Check that the user can access the page after login.
    $this->drupalGet('node');
    $this->assertSession()->statusCodeEquals(200);
    self::assertTrue($this->drupalUserIsLoggedIn($this->privilegedUser));

    // Wait within the timeout period.
    sleep(5);

    // Check we are still logged in.
    $this->drupalGet('node');
    $this->assertSession()->statusCodeEquals(200);
    self::assertTrue($this->drupalUserIsLoggedIn($this->privilegedUser));
  }

  /**
   * Tests a user is logged out and denied access to admin pages.
   */
  public function testAutologoutDefaultTimeoutAccessDeniedToAdmin() {
    $autologout_settings = $this->configFactory->getEditable('autologout.settings');
    // Enforce auto logout of admin pages.
    $autologout_settings->set('enforce_admin', FALSE)->save();

    // Check that the user can access the page after login.
    $this->drupalGet('admin/reports/status');
    $this->assertSession()->statusCodeEquals(200);
    self::assertTrue($this->drupalUserIsLoggedIn($this->privilegedUser));

    // Wait for timeout period to elapse.
    sleep(15);

    // Check we are now logged out.
    $this->drupalGet('admin/reports/status');
    $this->assertSession()->statusCodeEquals(403);
    self::assertFalse($this->drupalUserIsLoggedIn($this->privilegedUser));
    $this->assertSession()->pageTextContains(
      $this->t('You have been logged out due to inactivity.')
    );
  }

  /**
   * Tests integration with the remember me module.
   *
   * Users who checked remember_me on login should never be logged out.
   */
  public function testNoAutologoutWithRememberMe() {
    // Set the remember_me module data bit to TRUE.
    $this->userData->set(
      'remember_me',
      $this->privilegedUser->id(),
      'remember_me',
      TRUE
    );

    // Check that the user can access the page after login.
    $this->drupalGet('node');
    $this->assertSession()->statusCodeEquals(200);
    self::assertTrue($this->drupalUserIsLoggedIn($this->privilegedUser));

    // Wait for timeout period to elapse.
    sleep(15);

    // Check we are still logged in.
    $this->drupalGet('node');
    $this->assertSession()->statusCodeEquals(200);
    self::assertTrue($this->drupalUserIsLoggedIn($this->privilegedUser));
  }

  /**
   * Tests the behaviour of custom message displayed on autologout.
   */
  public function testCustomMessage() {
    $autologout_settings = $this->configFactory->getEditable('autologout.settings');
    $inactivity_message = 'Custom message for test';

    // Update message string in configuration.
    $autologout_settings->set('inactivity_message', $inactivity_message)
      ->save();

    // Set time out for 5 seconds.
    $autologout_settings->set('timeout', 5)->save();

    // Wait for 20 seconds for timeout.
    sleep(20);

    // Access the admin page and verify user is logged out and custom message
    // is displayed.
    $this->drupalGet('admin/reports/status');
    self::assertFalse($this->drupalUserIsLoggedIn($this->privilegedUser));
    $this->assertSession()->pageTextContains($inactivity_message);
  }

  /**
   * Tests the user not being logged out if autologout is disabled.
   */
  public function testAutologoutDisabled() {
    $autologout_settings = $this->configFactory->getEditable('autologout.settings');

    // Disable autologout.
    $autologout_settings->set('enabled', FALSE)
      ->save();

    // Set time out for 5 seconds.
    $autologout_settings->set('timeout', 5)->save();

    // Wait for 20 seconds for timeout.
    sleep(20);

    // Check if we are still logged in.
    $this->drupalGet('node');
    $this->assertSession()->statusCodeEquals(200);
    self::assertTrue($this->drupalUserIsLoggedIn($this->privilegedUser));
  }

  /**
   * Tests the behaviour of application when Autologout is enabled for admin.
   */
  public function testAutologoutAdminPages() {
    $autologout_settings = $this->configFactory->getEditable('autologout.settings');
    // Enforce auto logout of admin pages.
    $autologout_settings->set('enforce_admin', TRUE)->save();
    // Set time out as 5 seconds.
    $autologout_settings->set('timeout', 5)->save();
    // Verify admin should not be logged out.
    $this->drupalGet('admin/reports/status');
    $this->assertSession()->statusCodeEquals('200');

    // Wait until timeout.
    sleep(20);

    // Verify admin should be logged out.
    $this->drupalGet('admin/reports/status');
    self::assertFalse($this->drupalUserIsLoggedIn($this->privilegedUser));
    $this->assertSession()->pageTextContains(
      $this->t('You have been logged out due to inactivity.')
    );
  }

  /**
   * Tests that the settings update is reflected on cached front pages.
   */
  public function testAutologoutSettingsCache() {
    // Visit the user profile page to cache it and test JS timeout variable.
    $this->drupalGet('user');
    $jsSettings = $this->getDrupalSettings();
    $this->assertEquals(10000, $jsSettings['autologout']['timeout']);

    // Update the timeout variable and reload the user profile page.
    $autologout_settings = $this->configFactory->getEditable('autologout.settings');
    $autologout_settings->set('timeout', 5)->save();
    $this->drupalGet('user');

    // Test that the JS timeout variable is updated.
    $jsSettings = $this->getDrupalSettings();
    $this->assertEquals(5000, $jsSettings['autologout']['timeout']);
  }

}
