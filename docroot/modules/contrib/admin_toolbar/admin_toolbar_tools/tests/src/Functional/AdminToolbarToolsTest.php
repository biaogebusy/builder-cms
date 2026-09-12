<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_toolbar_tools\Functional;

use Drupal\Tests\admin_toolbar\Traits\AdminToolbarHelperTestTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Test the Admin Toolbar Tools standalone module.
 *
 * Basic test of the module with no other Admin Toolbar module and only with the
 * ones required from Drupal core. Enabled the 'menu_ui' module to check the
 * 'ExtraLinks' logic still loads with the core Toolbar module disabled.
 *
 * @group admin_toolbar
 * @group admin_toolbar_tools
 *
 * @see \Drupal\admin_toolbar_tools\Form\AdminToolbarToolsSettingsForm
 */
class AdminToolbarToolsTest extends BrowserTestBase {

  use AdminToolbarHelperTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'admin_toolbar_tools',
    // Enable the 'menu_ui' module to be able to test the 'All menus' link.
    'menu_ui',
  ];

  /**
   * A user with access to the Admin Toolbar settings form permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Access to the site's configuration admin pages and menus, so extra links
    // could be tested on the admin index page.
    $permissions = [
      'access administration pages',
      // Required to test module's settings form.
      'administer site configuration',
      // Permission needed to test links provided by the 'menu_ui' module.
      'administer menu',
    ];
    // Create an admin user with access to several admin sections or routes, so
    // the extra links added by the admin toolbar tools module could be tested.
    $this->adminUser = $this->drupalCreateUser($permissions);
  }

  /**
   * Test Admin Toolbar Tools standalone admin settings form and index pages.
   *
   * Login as an admin user, go to the 'Admin Toolbar Tools settings' form page,
   * change all the values, submit the form and check the expected values are
   * displayed on the admin index page:
   * - Module's CSS is not loaded since the 'toolbar' module is disabled.
   * - The 'All menus' link when the 'max_bundle_number' value is lower than the
   *   number of un-deletable default core menus (5).
   * - All the extra links added by the module on the 'Admin Index' page, such
   *   flush cache, logout, etc...
   *
   * This test only does a very basic check of the maximum number of bundles
   * just to ensure the setting is saved and applied as expected. More extensive
   * tests of this feature are done in Functional tests for class 'ExtraLinks'.
   *
   * Since by default the core menu system has 5 un-deletable menus, testing
   * the links provided by the 'menu_ui' with the 'max_bundle_number' setting
   * can be done straight away without creating any custom entities or bundles.
   *
   * @see admin_toolbar_tools_toolbar()
   * @see \Drupal\admin_toolbar_tools\Plugin\Derivative\ExtraLinks
   * @see \Drupal\Tests\admin_toolbar_tools\Functional\AdminToolbarToolsExtraLinksTest
   */
  public function testAdminToolbarToolsAdminIndex(): void {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Log in as an admin user to test admin pages.
    $this->drupalLogin($this->adminUser);

    // Check the CSS file of the module is *not* loaded as expected, since the
    // 'toolbar' module is disabled, so its hook is not triggered.
    $admin_toolbar_tools_css = 'admin_toolbar_tools.css';
    $assert->responseNotContains($admin_toolbar_tools_css);

    /* Test default values to compare with the ones after the changes. */

    // Test the 'Admin Toolbar Tools settings' page form submission and fields.
    $this->drupalGet('admin/config/user-interface/admin-toolbar-tools');

    // Ensure the 'show_local_tasks' field is *not* in the form since the
    // 'toolbar' module is disabled.
    $assert->responseNotContains("show-local-tasks");
    $assert->elementsCount('css', 'form.admin-toolbar-tools-settings .form-item', 1);

    // Submit the form with updated values.
    $this->submitForm([
      // Set 'max_bundle_number' to a value lower than the number of
      // un-deletable default core menus (5) to do a simple test/check.
      'max_bundle_number' => 2,
    ], 'Save configuration');
    // Check the form submission was successful.
    $assert->pageTextContains('The configuration options have been saved.');

    /* Test updated values. */

    // Test the 'Admin Index' page shows the expected links.
    $this->drupalGet('admin/index');

    // Use a regex to check in the response HTML code that the extra links for
    // menus are displayed with the expected tags, IDs and CSS classes. This
    // also ensures the links have the expected order.
    $assert->responseMatches('/<div class="panel">[\r\n ]*<h3 class="panel__title">Admin Toolbar Extra Tools<\/h3>[\r\n ]*<div class="panel__content">[\r\n ]*<dl class="list-group">[\r\n ]*<dt class="list-group__link"><a href=".*\/admin\/structure\/menu\/manage\/admin\/add">Add link<\/a><\/dt>([\r\n].*)+<dt class="list-group__link"><a href=".*\/admin\/structure\/menu\/manage\/footer\/add">Add link<\/a><\/dt>([\r\n].*)+<dt class="list-group__link"><a href=".*\/admin\/structure\/menu\/add">Add menu<\/a><\/dt>([\r\n].*)+<dt class="list-group__link"><a href=".*\/admin\/structure\/menu\/manage\/admin" title="Administrative task links">Administration<\/a><\/dt>[\r\n ]*<dd class="list-group__description">Administrative task links<\/dd>[\r\n ]*<dt class="list-group__link"><a href=".*\/admin\/config\/user-interface\/admin-toolbar-tools" title="Configure the Admin Toolbar Tools module.">Admin Toolbar Tools<\/a><\/dt>[\r\n ]*<dd class="list-group__description">Configure the Admin Toolbar Tools module.<\/dd>[\r\n ]*<dt class="list-group__link"><a href=".*\/admin\/structure\/menu">All menus<\/a><\/dt>([\r\n].*)+<dt class="list-group__link"><a href=".*\/admin\/flush\?token=.*">Flush all caches<\/a><\/dt>([\r\n].*)+<dt class="list-group__link"><a href=".*\/admin\/flush\/cssjs\?token=.*">Flush CSS and JavaScript<\/a><\/dt>([\r\n].*)+<dt class="list-group__link"><a href=".*\/admin\/flush\/plugin\?token=.*">Flush plugins cache<\/a><\/dt>([\r\n].*)+<dt class="list-group__link"><a href=".*\/admin\/flush\/rendercache\?token=.*">Flush render cache<\/a><\/dt>([\r\n].*)+<dt class="list-group__link"><a href=".*\/admin\/flush\/menu\?token=.*">Flush routing and links cache<\/a><\/dt>([\r\n].*)+<dt class="list-group__link"><a href=".*\/admin\/flush\/static-caches\?token=.*">Flush static cache<\/a><\/dt>([\r\n].*)+<dt class="list-group__link"><a href=".*\/admin\/flush\/twig\?token=.*">Flush twig cache<\/a><\/dt>([\r\n].*)+<dt class="list-group__link"><a href=".*\/admin\/structure\/menu\/manage\/footer" title="Site information links">Footer<\/a><\/dt>[\r\n ]*<dd class="list-group__description">Site information links<\/dd>[\r\n ]*<dt class="list-group__link"><a href=".*\/">Front page<\/a><\/dt>([\r\n].*)+<dt class="list-group__link"><a href=".*\/admin\/index">Index<\/a><\/dt>([\r\n].*)+<dt class="list-group__link"><a href=".*\/user\/logout(\?token=.*)?">Logout<\/a><\/dt>([\r\n].*)+<dt class="list-group__link"><a href=".*\/admin\/flush\/theme_rebuild\?token=.*">Rebuild theme registry<\/a><\/dt>([\r\n].*)+<dt class="list-group__link"><a href=".*\/run-cron\?token=.*">Run cron<\/a><\/dt>([\r\n].*)+<\/dl>[\r\n ]*<\/div>[\r\n ]*<\/div>/');

  }

}
