<?php

namespace Drupal\Tests\social_api\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Defines a base class for testing Social Auth implementers.
 */
abstract class SocialApiTestBase extends BrowserTestBase {

  use StringTranslationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['social_api'];

  /**
   * The installation profile to use with this test.
   *
   * @var string
   */
  protected $profile = 'minimal';

  /**
   * A test user with no permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $noPermsUser;

  /**
   * A test user with corresponding permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * The permissions for the admin user.
   *
   * @var array
   */
  protected array $adminUserPermissions;

  /**
   * The module machine name that is being tested.
   *
   * @var string
   */
  protected string $module;

  /**
   * The default theme.
   *
   * @var mixed
   */
  protected $defaultTheme = 'stark';

  /**
   * The machine name of the provider the module works with.
   *
   * @var null|string
   */
  protected ?string $provider = NULL;

  /**
   * The module type (social_auth, social_post).
   *
   * @var string
   */
  protected string $moduleType;

  /**
   * Settings fields to test.
   *
   * @var array
   */
  protected array $fields = [];

  /**
   * The settings to be saved.
   *
   * @var array
   */
  protected array $edit;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a non-administrative user.
    $this->noPermsUser = $this->drupalCreateUser();

    // Create an administrative user.
    $this->adminUser = $this->drupalCreateUser($this->adminUserPermissions);
  }

  /**
   * Check that module is available in integration list.
   */
  public function checkIsAvailableInIntegrationList() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/social-api/' . $this->moduleType);

    // Assert that module is enabled in integrations list.
    $this->assertSession()->pageTextContains($this->module);
  }

  /**
   * Test for configuration page.
   */
  public function checkPermissionForSettingsPage() {
    $assert = $this->assertSession();

    // Verifies that permissions are applied to the defined paths.
    $forbidden_paths = [
      "/admin/config/social-api/$this->moduleType/$this->provider",
    ];

    // Checks each of the paths to make sure we don't have access. At this point
    // we haven't logged in any users, so the client is anonymous.
    foreach ($forbidden_paths as $path) {
      $this->drupalGet($path);
      $assert->statusCodeEquals(403);
    }

    // Logs in user with no permissions.
    $this->drupalLogin($this->noPermsUser);

    // Should be the same result for forbidden paths, since the user needs
    // special permissions for these paths.
    foreach ($forbidden_paths as $path) {
      $this->drupalGet($path);
      $assert->statusCodeEquals(403);
    }

    // Logs in user with permissions.
    $this->drupalLogin($this->adminUser);

    // Forbidden paths aren't forbidden any more.
    foreach ($forbidden_paths as $unforbidden) {
      $this->drupalGet($unforbidden);
      $assert->statusCodeEquals(200);
    }

    // Now that we have the admin user logged in, check the menu links.
    $this->drupalGet('/admin/config/social-api/' . $this->moduleType . '/' . $this->provider);

    foreach ($this->fields as $field) {
      $assert->fieldExists($field);
    }
  }

  /**
   * Tests module settings form submission.
   */
  public function checkSettingsFormSubmission() {
    $this->drupalLogin($this->adminUser);
    $path = 'admin/config/social-api/' . $this->moduleType . '/' . $this->provider;

    $this->drupalGet($path);
    $this->submitForm($this->edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
  }

}
