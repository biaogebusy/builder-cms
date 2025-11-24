<?php

namespace Drupal\Tests\admin_toolbar\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test the existence of Admin Toolbar module.
 *
 * @group admin_toolbar
 */
class AdminToolbarAlterTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'toolbar',
    'breakpoint',
    'admin_toolbar',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A test user with permission to access the administrative toolbar.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $perms = [
      'access toolbar',
      'access administration pages',
      'administer site configuration',
      'administer permissions',
      'administer users',
      'administer account settings',
    ];

    // Create and log in an administrative user.
    $this->adminUser = $this->drupalCreateUser($perms);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests for a the hover of sub menus.
   *
   * @return void
   *   Nothing to return.
   */
  public function testAdminToolbar() {
    // Assert that expanded links are present in the HTML.
    $this->assertSession()->responseContains('class="toolbar-icon toolbar-icon-user-admin-index"');
  }

}
