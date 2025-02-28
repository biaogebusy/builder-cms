<?php

namespace Drupal\Tests\page_load_progress\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Core\Url;

/**
 * Tests for the page_load_progress module's admin settings.
 *
 * @group page_load_progress
 */
class PageLoadProgressAdminSettingsTest extends BrowserTestBase {
  /**
   * User account with administrative permissions.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['page_load_progress'];

  /**
   * The installation profile to use with this test.
   *
   * @var string
   */
  protected $profile = 'minimal';

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return [
      'name' => 'Page Load Progress admin settings',
      'description' => 'Tests the Page Load Progress admin settings.',
      'group' => 'Page Load Progress',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Admin user account only needs a subset of admin permissions.
    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'access administration pages',
      'administer permissions',
      'administer page load progress',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test menu link and permissions.
   */
  public function testAdminPages() {

    // Verify admin link.
    $this->drupalGet(Url::fromRoute('system.admin_config_ui'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Page Load Progress');

    // Verify route is valid.
    $this->drupalGet(Url::fromRoute('page_load_progress.admin_settings'));
    $this->assertSession()->statusCodeEquals(200);

    // Verify that there's no access bypass.
    $this->drupalLogout();
    $this->drupalGet(Url::fromRoute('system.admin_config_ui'));
    $this->assertSession()->statusCodeEquals(403);
  }

}
