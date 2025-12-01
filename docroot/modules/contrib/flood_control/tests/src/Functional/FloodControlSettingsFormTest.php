<?php

namespace Drupal\Tests\FloodControl\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test the flood control settings form.
 *
 * @group flood_control
 */
class FloodControlSettingsFormTest extends BrowserTestBase {

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

    $this->webUser = $this->createUser();

    $this->adminUser = $this->drupalCreateUser([]);
    $this->adminUser->addRole($this->createAdminRole('admin', 'admin'));
    $this->adminUser->save();

    $this->moduleInstaller = $this->container->get('module_installer');
  }

  /**
   * Test access to the settings form.
   */
  public function testSettingsFormAccess() {
    // Anonymous users are not allowed to access the settings form.
    $this->drupalGet('/admin/config/people/flood-control');
    $this->assertSession()->statusCodeEquals(403);

    // Logged in users without the 'administer site configuration' permission
    // are are not allowed to access the settings form.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('/admin/config/people/flood-control');
    $this->assertSession()->statusCodeEquals(403);

    // Users with the 'administer site configuration' permission can access the
    // settings form.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/people/flood-control');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Test the settings form.
   */
  public function testSettingsForm() {
    // Log in as admin user.
    $this->drupalLogin($this->adminUser);
    // Go to the settings form.
    $this->drupalGet('/admin/config/people/flood-control');

    // The contact module is not enabled so this fields should not be shown.
    $this->assertSession()->fieldNotExists('contact_threshold_limit');
    $this->assertSession()->fieldNotExists('contact_threshold_window');

    // Submit the form.
    $this->submitForm([
      'ip_limit' => 500,
      'ip_window' => 86400,
      'user_limit' => 10,
      'user_window' => 60,
    ], 'Save configuration');

    // Check that the success message is shown.
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Check that the configured values are correctly saved in the config.
    $user_flood = $this->config('user.flood');
    $this->assertEquals(500, $user_flood->get('ip_limit'));
    $this->assertEquals(86400, $user_flood->get('ip_window'));
    $this->assertEquals(10, $user_flood->get('user_limit'));
    $this->assertEquals(60, $user_flood->get('user_window'));

    // Enable the contact module.
    $this->moduleInstaller->install(['contact']);

    // Go to the settings form.
    $this->drupalGet('/admin/config/people/flood-control');

    // The contact module is now enabled, so the contact fields should now be
    // visible.
    $this->assertSession()->fieldExists('contact_threshold_limit');
    $this->assertSession()->fieldExists('contact_threshold_window');

    // Submit the form.
    $this->submitForm([
      'contact_threshold_limit' => 125,
      'contact_threshold_window' => 1800,
    ], 'Save configuration');

    // Check that the success message is shown.
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Check that the configured values are correctly saved in the config.
    $user_flood = $this->config('contact.settings');
    $this->assertEquals(125, $user_flood->get('flood.limit'));
    $this->assertEquals(1800, $user_flood->get('flood.interval'));
  }

}
