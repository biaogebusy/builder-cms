<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_toolbar_search\FunctionalJavascript;

/**
 * Test the JS autocomplete search feature of the module.
 *
 * @group admin_toolbar
 * @group admin_toolbar_search
 */
class AdminToolbarSearchTest extends AdminToolbarSearchTestBase {

  /**
   * Test the standalone search functionality, *without* 'admin_toolbar_tools'.
   *
   * Login as an admin user, wait for the search to load in the toolbar and
   * check several autocomplete suggestions: submit several search queries and
   * check the suggestions contain the expected results.
   *
   * @return void
   *   Nothing to return.
   */
  public function testAdminToolbarSearch() {
    /** @var \Drupal\FunctionalJavascriptTests\JSWebAssert $assert_session */
    $assert_session = $this->assertSession();

    // Check several autocomplete suggestions, such as 'perform' or 'types'.
    // The permissions: 'access administration pages' and 'administer site
    // configuration', are required to test these routes.
    $search_queries = [
      // Check system routes.
      [
        // The 'query' is the text submitted in the autocomplete search field.
        'query' => 'basic',
        // The 'expected' is the string that should be contained in the HTML
        // returned in the expected suggestions (links, HTML markup, etc...).
        'expected' => 'admin/config/system/site-information',
        // A single result should be displayed.
        'expected_count' => 1,
      ],
      [
        'query' => 'perform',
        'expected' => 'admin/config/development/performance',
        // A single result should be displayed.
        'expected_count' => 1,
      ],
      [
        'query' => 'develop',
        'expected' => 'admin/config/development/maintenance',
        // Five results should be displayed.
        'expected_count' => (version_compare(\Drupal::VERSION, '10', '<')) ? 4 : 5,
      ],
      [
        'query' => 'file',
        'expected' => '/admin/config/media/file-system',
        // A single result should be displayed.
        'expected_count' => 1,
      ],
      [
        'query' => 'menus',
        // The 'node' module is required to test this link: Content types.
        'expected' => 'admin/structure/menu',
        // A single result should be displayed.
        'expected_count' => 1,
      ],
      // Check the links defined by the 'Admin Toolbar' module.
      [
        // cSpell:ignore toolb
        'query' => 'admin toolb',
        'expected' => 'admin/config/user-interface/admin-toolbar',
        // Two results should be displayed: admin toolbar and search settings.
        'expected_count' => 2,
      ],
      [
        'query' => 'admin toolbar sear',
        'expected' => 'admin/config/user-interface/admin-toolbar-search',
        // A single result should be displayed.
        'expected_count' => 1,
      ],
    ];

    // Execute all search queries and compare with the expected results.
    foreach ($search_queries as $search_query) {
      $this->assertSuggestionsContain($search_query['query'], $search_query['expected'], $search_query['expected_count']);
      // Check the expected url is found in the toolbar menu which is the reason
      // why it is displayed in the search suggestions.
      $this->assertAdminToolbarMenuLinkExists($search_query['expected']);
    }

    // Test that the route 'admin_toolbar.search' returns an empty array, but
    // not an error, when module 'admin_toolbar_tools' is disabled.
    $this->drupalGet('admin/admin-toolbar-search');
    $assert_session->responseContains('[]');

  }

}
