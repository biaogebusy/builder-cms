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

    $this->adminUser = $this->drupalCreateUser(['administer flood unblock']);

    $this->moduleInstaller = $this->container->get('module_installer');
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
    $this->assertSession()->statusCodeEquals(200);

    // Check that the success message is shown.
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Check that the configured values are correctly saved in the config.
    $user_flood = $this->config('user.flood');
    $this->assertEquals(500, $user_flood->get('ip_limit'));
    $this->assertEquals(86400, $user_flood->get('ip_window'));
    $this->assertEquals(10, $user_flood->get('user_limit'));
    $this->assertEquals(60, $user_flood->get('user_window'));

    // Enable the contact module and confirm that it installed correctly.
    $installed = $this->moduleInstaller->install(['contact']);
    $this->assertTrue($installed);

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
    $this->assertSession()->statusCodeEquals(200);

    // Check that the success message is shown.
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Check that the correct values are displayed in the form.
    $this->assertSession()->fieldValueEquals('contact_threshold_limit', 125);
    $this->assertSession()->fieldValueEquals('contact_threshold_window', 1800);
  }

}
