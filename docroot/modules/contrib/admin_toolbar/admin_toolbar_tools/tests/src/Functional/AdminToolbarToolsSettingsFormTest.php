<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_toolbar_tools\Functional;

use Drupal\Tests\admin_toolbar\Traits\AdminToolbarHelperTestTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Test the Admin Toolbar Tools settings form.
 *
 * @group admin_toolbar
 * @group admin_toolbar_tools
 *
 * @see \Drupal\admin_toolbar_tools\Form\AdminToolbarToolsSettingsForm
 */
class AdminToolbarToolsSettingsFormTest extends BrowserTestBase {

  use AdminToolbarHelperTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'admin_toolbar',
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

    // Access to the toolbar and site's configuration admin pages, so basic
    // system config routes could be tested in the admin toolbar menu links.
    $permissions = [
      'access toolbar',
      'access administration pages',
      'administer site configuration',
      // Permission needed to test links provided by the 'menu_ui' module.
      'administer menu',
    ];
    // Create an admin user with access to the admin toolbar and several admin
    // sections or routes, so the extra links added by the admin toolbar tools
    // module could be tested.
    $this->adminUser = $this->drupalCreateUser($permissions);
  }

  /**
   * Test backend Admin Toolbar Tools settings form fields and submission.
   *
   * Login as an admin user, go to the 'Admin Toolbar Tools settings' form,
   * change all the values, submit the form and check the expected values are
   * displayed:
   * - The 'Tools' menu link in the toolbar with its CSS file.
   * - The 'All menus' link when the 'max_bundle_number' value is lower than the
   *   number of un-deletable default core menus (5).
   * - The 'Local Tasks' tab and links when enabled.
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
   * @see \Drupal\admin_toolbar_tools\AdminToolbarToolsHelper::buildLocalTasksToolbar()
   * @see \Drupal\Tests\admin_toolbar_tools\Functional\AdminToolbarToolsExtraLinksTest
   */
  public function testAdminToolbarToolsSettingsForm(): void {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Log in as an admin user to test admin pages.
    $this->drupalLogin($this->adminUser);

    // Assert that special menu items are present in the HTML.
    $assert->responseContains('class="toolbar-icon toolbar-icon-admin-toolbar-tools-flush"');

    // Ensure Admin Toolbar Tools is correctly loaded with the 'Tools' menu link
    // displayed first with the correct icon in the toolbar and its CSS file.
    $this->assertAdminToolbarMenuLinkExists('/', 'Front page', 1, 'toolbar-icon toolbar-icon-admin-toolbar-tools-help');
    // Check the CSS file of the module is loaded as expected.
    $admin_toolbar_tools_css = 'admin_toolbar_tools/css/admin_toolbar_tools.css';
    $assert->responseContains($admin_toolbar_tools_css);

    /* Test default values to compare with the ones after the changes. */

    // The 'All menus' link should not be displayed by default, since the
    // default value of 'max_bundle_number' (20) is greater than the number
    // of un-deletable default core menus (5).
    $test_all_menus_label = 'All menus';
    $assert->responseNotContains($test_all_menus_label);

    // Check the 'Local Tasks' tab and links are not displayed by default.
    $local_tasks_toolbar_tab_css_class = 'local-tasks-toolbar-tab';
    $local_tasks_toolbar_icon_css_class = 'toolbar-icon-local-tasks';
    $assert->responseNotContains($local_tasks_toolbar_tab_css_class);
    $assert->responseNotContains($local_tasks_toolbar_icon_css_class);

    // Test the 'Admin Toolbar Tools settings' page form submission and fields.
    $this->drupalGet('admin/config/user-interface/admin-toolbar-tools');
    // Submit the form with updated values.
    $this->submitForm([
      // Set 'max_bundle_number' to a value lower than the number of
      // un-deletable default core menus (5) to do a simple test/check.
      'max_bundle_number' => 2,
      // Enable the 'Local Tasks' tab and links in the toolbar.
      'show_local_tasks' => TRUE,
    ], 'Save configuration');
    // Check the form submission was successful.
    $assert->pageTextContains('The configuration options have been saved.');

    /* Test updated values. */

    // The 'All menus' link should now be displayed, since the value of
    // 'max_bundle_number' (2) is lower than the number of un-deletable default
    // core menus (5).
    // cSpell:ignore linksentity
    $this->assertAdminToolbarMenuLinkExists('admin/structure/menu', $test_all_menus_label, 1, 'toolbar-icon toolbar-icon-admin-toolbar-tools-extra-linksentity-menu-collection');

    // Use a regex to check in the response HTML code that the 'Local Tasks' tab
    // and links are displayed with the expected tags, IDs and CSS classes. This
    // also ensures the links have the expected order and the 'is-active' class.
    $assert->responseMatches('/<div class="' . $local_tasks_toolbar_tab_css_class . ' toolbar-tab">[\r\n ]*<a .*class="toolbar-icon ' . $local_tasks_toolbar_icon_css_class . '.*" id="toolbar-item-admin-toolbar-local-tasks".*>Local Tasks<\/a>[\r\n ]*<div id="toolbar-item-admin-toolbar-local-tasks-tray".*>[\r\n ]*<nav class="toolbar-lining clearfix" role="navigation">[\r\n ]*<ul class="toolbar-menu"><li><a href=".*\/admin\/config\/user-interface\/admin-toolbar">Toolbar settings<\/a><\/li><li><a href=".*\/admin\/config\/user-interface\/admin-toolbar-tools" class="is-active">Tools settings<\/a><\/li><\/ul><\/nav>[\r\n ]*<\/div>/');

  }

}
