<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_toolbar_tools\FunctionalJavascript;

use Drupal\Tests\admin_toolbar_search\FunctionalJavascript\AdminToolbarSearchTestBase;

/**
 * Test the search integration of Admin Toolbar Tools with Admin Toolbar Search.
 *
 * @group admin_toolbar
 * @group admin_toolbar_tools
 *
 * @see admin_toolbar_search/js/admin_toolbar_search.js
 */
class AdminToolbarToolsSearchTest extends AdminToolbarSearchTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Test admin_toolbar_search.
    'admin_toolbar_search',
    // This test is focused on the integration with Admin Toolbar Tools.
    'admin_toolbar_tools',
    // The following modules are required to test the 'ExtraLinks' feature.
    'menu_ui',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    // Call the parent setUp() to create the admin user and log in.
    parent::setUp();

    // Set 'max_bundle_number' to a value lower than the number of un-deletable
    // default core menus (5) to do a simple test/check.
    $max_bundle_number = 2;
    $this->config('admin_toolbar_tools.settings')
      ->set('max_bundle_number', $max_bundle_number)
      ->save();

    // Rebuild menu items: Admin Toolbar Tools requires a cache rebuild to
    // update the toolbar menu with the new config setting. See the submitForm()
    // method in AdminToolbarToolsSettingsForm.
    drupal_flush_all_caches();

    // Refresh the page to load the new config setting so the 'All menus' link
    // is available in the toolbar menu and search suggestions.
    $this->drupalGet('admin');
  }

  /**
   * Test the search functionality *with* 'admin_toolbar_tools' enabled.
   *
   * Test the JS code of the 'admin_toolbar_search' module that integrates with
   * 'admin_toolbar_tools' to load extra links via AJAX and include them in the
   * search suggestions.
   *
   * Test the integration with Admin Toolbar Tools:
   * - Ensure the extra links are loaded properly with an AJAX request to
   *   module's controller, triggered only once, when the search input field is
   *   focused for the first time.
   * - Ensure the search suggestions include the expected menu extra links
   *   collected with the 'SearchLinks' class.
   * - Check the search currently filters the links by label and url.
   *
   * @return void
   *   Nothing to return.
   */
  public function testAdminToolbarToolsSearch() {
    // Test the extra links AJAX request is working as expected for a simple
    // query with 'menu_ui' links. Use the 'account' menu links for testing
    // since it should always be present and the last of the default links.
    $test_menu_id = 'account';

    // Check several autocomplete suggestions, such as 'add', 'edit' or 'all'.
    // The permissions: 'access administration pages' and 'administer menu', are
    // required to test these routes.
    $search_queries = [
      // Check 'menu_ui' links, under 'Structure' > 'Menus'.
      [
        // Test the search suggestions for the 'account' menu links returns a
        // single link since the word 'edit' is found in filtered labels or urls
        // of the items loaded by SearchLinks.
        'query' => 'edit ' . $test_menu_id,
        'expected' => 'admin/structure/menu/manage/' . $test_menu_id,
        // A single result should be displayed.
        'expected_count' => 1,
      ],
      [
        // Test the 'add' link has a single suggestion and does not appear in
        // the toolbar menu links, since it was loaded via AJAX through
        // 'SearchLinks'.
        'query' => 'add menu ' . $test_menu_id,
        'expected' => 'admin/structure/menu/manage/' . $test_menu_id . '/add',
        // A single result should be displayed.
        'expected_count' => 1,
      ],
      [
        // Test the 'All menus' link only has a single suggestion, which means
        // the search is filtering on the link label and url.
        'query' => 'all menus',
        'expected' => 'admin/structure/menu',
        // A single result should be displayed.
        'expected_count' => 1,
      ],
    ];

    // Execute all search queries and compare with the expected results.
    foreach ($search_queries as $search_query) {
      $this->assertSuggestionsContain(
        $search_query['query'],
        $search_query['expected'],
        $search_query['expected_count']
      );
    }

    // Test the 'add' link has a single suggestion and does *not* appear in the
    // toolbar menu links, since it was loaded via AJAX through 'SearchLinks'.
    $this->assertAdminToolbarMenuLinkNotExists($test_menu_id . '/add');

    // Additional test to check query strings are not picked up in search
    // results. Expecting no result for this query.
    $this->assertSuggestionsNotContain('token', 'logout');

  }

}
