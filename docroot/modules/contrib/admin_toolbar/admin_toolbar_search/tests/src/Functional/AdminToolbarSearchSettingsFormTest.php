<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_toolbar_search\Functional;

use Drupal\admin_toolbar_search\Constants\AdminToolbarSearchConstants;
use Drupal\Tests\BrowserTestBase;

/**
 * Test the Admin Toolbar Search settings form and module's permission.
 *
 * @group admin_toolbar
 * @group admin_toolbar_search
 */
class AdminToolbarSearchSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'admin_toolbar_search',
    // Enable the block module to be able to test module's local tasks.
    'block',
    // The admin toolbar and tools modules are required to check the display of
    // the three menu local tasks (tabs) on the admin settings form page.
    'admin_toolbar',
    'admin_toolbar_tools',
  ];

  /**
   * A user with access to the Admin Toolbar settings form and search.
   *
   * Permissions to access the site's admin backend form pages and the 'use
   * admin toolbar search' permission defined by the module.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A test user without the 'use admin toolbar search' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $noAccessUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /* Create custom objects. */

    // Create a block for testing module's primary local tasks.
    $this->createPrimaryLocalTasksBlock();

    /* Setup users for testing access and permissions. */

    // Access to the toolbar and site's configuration admin pages, so the 'Admin
    // Toolbar Search settings' form page could be tested.
    $permissions = [
      'access toolbar',
      'access administration pages',
      'administer site configuration',
    ];
    // Create a user with limited permissions.
    $this->noAccessUser = $this->drupalCreateUser($permissions);
    // This permission is needed to test the inclusion of the JS libraries.
    $permissions[] = 'use admin toolbar search';
    // Create a user with access to the admin toolbar search.
    $this->adminUser = $this->drupalCreateUser($permissions);
  }

  /**
   * Helper function to create a block to test module's primary local tasks.
   */
  private function createPrimaryLocalTasksBlock(): void {
    $values = [
      // A unique ID for the block instance.
      'id' => $this->defaultTheme . '_primary_local_tasks',
      // The plugin block id as defined in the class.
      'plugin' => 'local_tasks_block',
      // The machine name of the theme region.
      'region' => 'highlighted',
      'settings' => [
        'label' => 'Primary tabs',
        'label_display' => '0',
        'primary' => TRUE,
        'secondary' => FALSE,
      ],
      // The machine name of the theme.
      'theme' => $this->defaultTheme,
      'visibility' => [],
      'weight' => 100,
    ];

    \Drupal::entityTypeManager()->getStorage('block')
      ->create($values)
      ->save();
  }

  /**
   * Test backend Admin Toolbar Search settings form fields and submission.
   *
   * Login as an admin user and browse to module's settings form page.
   * Change the configuration values, save and check the results several times:
   * - The inclusion of the CSS and JS files.
   * - The display of the search input field in the toolbar or in a menu item.
   * - The inclusion of the keyboard shortcut JS library.
   * - The search input field has the correct HTML IDs and attributes.
   * - The three modules local tasks (tabs) are displayed.
   *
   * Logout and login with a user without access to the search to test the
   * permission 'use admin toolbar search', ensure:
   * - The module's CSS and JS files are not loaded.
   * - The search input field is not displayed in the toolbar.
   * - The extra links search route is not accessible.
   *
   * @see admin_toolbar_search_toolbar()
   * @see \Drupal\admin_toolbar_search\Form\AdminToolbarSearchSettingsForm
   */
  public function testAdminToolbarSearchSettingsForm(): void {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Log in as an admin user to test module's settings form.
    $this->drupalLogin($this->adminUser);

    // Test the 'Admin Toolbar Search settings' page form submission and fields.
    $this->drupalGet('admin/config/user-interface/admin-toolbar-search');

    /* Test default values to compare with the ones after the changes. */

    // Check the default CSS and JS files of the module are loaded correctly.
    $admin_toolbar_search_css = 'admin_toolbar_search/css/admin_toolbar_search.css';
    $admin_toolbar_search_js = 'admin_toolbar_search/js/admin_toolbar_search.js';
    $assert->responseContains($admin_toolbar_search_css);
    $assert->responseContains($admin_toolbar_search_js);

    // Test 'enable_keyboard_shortcut'.
    $keyboard_shortcut_js = 'admin_toolbar_search/js/admin_toolbar_search.keyboard_shortcut.js';
    // Check the keyboard shortcut library is loaded by default.
    $assert->responseContains($keyboard_shortcut_js);
    // Check the settings variable to load extra links is enabled by default,
    // since Admin Toolbar Tools is enabled.
    $assert->responseContains('"adminToolbarSearch":{"loadExtraLinks":true}');

    // Check the search tab is displayed before the search field tab, with the
    // expected HTML IDs and attributes. Check the input fields have the correct
    // type, size, label, placeholder and title attributes.
    $assert->responseMatches('/<div id="' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_field_tab'] . '" class="toolbar-tab">[\n\r ]*<div class="js-form-item.*- form-no-label">[\n\r ]*<label for="' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_field_input'] . '" class="visually-hidden">Search<\/label>[\n\r ]*<input title="Keyboard shortcut: Alt \+ a" type="search" id="' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_field_input'] . '" size="30" maxlength="128" placeholder="Search for menu links \(Alt \+ a\)".*>[\n\r ]*<\/div>[\n\r ]*<div>[\n\r ]*<nav.*>[\n\r ]*<\/nav>[\n\r ]*<\/div>[\n\r ]*<\/div>[\n\r ]*<div id="' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_tab'] . '" class="toolbar-tab">[\n\r ]*<span class="toolbar-icon trigger toolbar-item" id="' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_toolbar_item'] . '".*>Search<\/span>[\n\r ]*<div id="' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_tray'] . '".*>[\n\r ]*<nav.*>[\n\r ]*<div class="js-form-item.*">[\n\r ]*<label for="' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_input'] . '">Search<\/label>[\n\r ]*<input title="Keyboard shortcut: Alt \+ a" type="search" id="' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_input'] . '" size="60" maxlength="128" placeholder="Search for menu links \(Alt \+ a\)".*>[\n\r ]*<\/div>[\n\r ]*<\/nav>[\n\r ]*<\/div>[\n\r ]*<\/div>/');

    // Change the value of 'display_menu_item' and submit the form.
    $this->submitForm([
      'display_menu_item' => TRUE,
    ], 'Save configuration');
    // Check the form submission was successful.
    $assert->pageTextContains('The configuration options have been saved.');

    /* Test updated values: 'display_menu_item: true'. */

    // Check the keyboard shortcut library is loaded by default.
    $assert->responseContains($keyboard_shortcut_js);

    // Check the search menu link tab is displayed with a toolbar tray, with the
    // expected HTML IDs and attributes. Check the input field has the correct
    // type, size, label, placeholder and title attributes.
    $assert->responseMatches('/<div id="' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_tab'] . '" class="toolbar-tab">[\n\r ]*<span class="toolbar-icon trigger toolbar-item" id="' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_toolbar_item'] . '".*>Search<\/span>[\n\r ]*<div id="' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_tray'] . '".*>[\n\r ]*<nav.*>[\n\r ]*<div class="js-form-item.*">[\n\r ]*<label for="' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_input'] . '">Search<\/label>[\n\r ]*<input title="Keyboard shortcut: Alt \+ a" type="search" id="' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_input'] . '" size="60" maxlength="128" placeholder="Search for menu links \(Alt \+ a\)".*>[\n\r ]*<\/div>[\n\r ]*<\/nav>[\n\r ]*<\/div>[\n\r ]*<\/div>/');

    // Check the HTML IDs of the search tab and input field are *not* displayed.
    $assert->responseNotContains(AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_field_tab']);
    $assert->responseNotContains(AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_field_input']);

    // Change the value of 'enable_keyboard_shortcut' and submit the form.
    $this->submitForm([
      'enable_keyboard_shortcut' => FALSE,
    ], 'Save configuration');
    // Check the form submission was successful.
    $assert->pageTextContains('The configuration options have been saved.');

    /* Test updated values: 'enable_keyboard_shortcut: false'. */

    // Check the keyboard shortcut library is not loaded.
    $assert->responseNotContains('admin_toolbar_search.keyboard_shortcut.js');
    // Check the default CSS and JS files of the module are still loaded.
    $assert->responseContains($admin_toolbar_search_css);
    $assert->responseContains($admin_toolbar_search_js);

    // Check the search input field has the correct label, placeholder and title
    // attributes, when the keyboard shortcut is disabled.
    $assert->responseMatches('/<div class="js-form-item.*">[\n\r ]*<label for="' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_input'] . '">Search<\/label>[\n\r ]*<input title="Type text to search for menu links in the admin toolbar." type="search" id="' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_input'] . '" size="60" maxlength="128" placeholder="Search for menu links".*>[\n\r ]*<\/div>/');
    $assert->responseNotContains('Alt \+ a');

    /* Test module's primary local tasks (tabs). */

    // Check the three modules local tasks (tabs) are displayed as expected.
    // @todo Revert the changes from DO-3559521 when support from D9 is dropped.
    $local_tasks_regex = '/<div.*-primary-local-tasks.*>([\r\n].*)+<a.*>Toolbar settings<\/a>.*[\r\n].*<a.*>Search settings(.*active tab.*)?<\/a>.*[\r\n].*<a.*>Tools settings<\/a>.*[\r\n].*([\r\n].*)+<\/div>/';
    $assert->responseMatches($local_tasks_regex);

    /* Test the permission: 'use admin toolbar search'. */

    // Test the extra links search route does not return an access denied error,
    // but an empty array, even with the 'admin_toolbar_tools' module enabled,
    // since the modules adding the extra links are all disabled in this test
    // ('field_ui', 'node', 'media', 'menu_ui', etc...).
    $this->drupalGet('/admin/admin-toolbar-search');
    $assert->responseContains('[]');

    // Logout the current session and login with a user *without* search access.
    $this->drupalLogout();
    $this->drupalLogin($this->noAccessUser);

    // Check the 'Search' tab is *not* displayed, since the user does not have
    // the required permission.
    // Check the CSS and JS files of the module are *not* loaded.
    $assert->responseNotContains($admin_toolbar_search_css);
    $assert->responseNotContains($admin_toolbar_search_js);

    // Check none of the HTML IDs selectors are found on the page, which means
    // the admin toolbar search is not loaded.
    foreach (AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS as $html_id) {
      $assert->responseNotContains($html_id);
    }

    // Ensure the extra links search route is not accessible without the
    // required permission.
    $this->drupalGet('/admin/admin-toolbar-search');
    $assert->statusCodeEquals(403);
  }

}
