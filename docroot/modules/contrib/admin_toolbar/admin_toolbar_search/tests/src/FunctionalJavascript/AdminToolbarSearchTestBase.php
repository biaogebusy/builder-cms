<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_toolbar_search\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\admin_toolbar\Traits\AdminToolbarHelperTestTrait;
use Drupal\admin_toolbar_search\Constants\AdminToolbarSearchConstants;

/**
 * Base class for testing the functionality of Admin Toolbar Search.
 *
 * @see \Drupal\Tests\admin_toolbar_search\FunctionalJavascript\AdminToolbarSearchTest
 *
 * @group admin_toolbar
 * @group admin_toolbar_search
 */
abstract class AdminToolbarSearchTestBase extends WebDriverTestBase {

  use AdminToolbarHelperTestTrait;

  /**
   * A user with access to the Admin Toolbar Search.
   *
   * An admin user with the permissions to access the site's admin backend form
   * pages and the 'use admin toolbar search' permission defined by the module.
   *
   * @var \Drupal\user\UserInterface
   */
  public $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'admin_toolbar',
    'admin_toolbar_search',
    // Required to test route: 'Menus'.
    'menu_ui',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    /* Setup users for the tests. */

    // Access to the toolbar and site's configuration admin pages, so basic
    // system config routes could be tested in the search autocomplete.
    $permissions = [
      'access toolbar',
      // Access to the menu links under '/admin/config/'.
      'access administration pages',
      'administer site configuration',
      // Permission needed to test links provided by the 'menu_ui' module.
      'administer menu',
      // This permission is needed to test the search autocomplete.
      'use admin toolbar search',
    ];

    // Create an admin user with access to the admin toolbar search.
    $this->adminUser = $this->drupalCreateUser($permissions);

    // Login with an admin user with access to the search in the toolbar.
    $this->drupalLogin($this->adminUser);

    // Wait for the necessary CSS and JS elements to load to test suggestions.
    $this->waitForAdminToolbarSearchVisible();
  }

  /**
   * Helper function to wait for Admin Toolbar Search HTML IDs to be visible.
   *
   * @return void
   *   Nothing to return.
   */
  protected function waitForAdminToolbarSearchVisible() {
    /** @var \Drupal\FunctionalJavascriptTests\JSWebAssert $assert_session */
    $assert_session = $this->assertSession();
    // Wait for the necessary CSS and JS elements to load to test suggestions.
    $assert_session->waitForElementVisible('css', '#' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_tab']);
    $assert_session->waitForElementVisible('css', '#' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_toolbar_item']);
    $assert_session->waitForElementVisible('css', '#' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_tray']);
    $assert_session->waitForElementVisible('css', '#' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_field_input']);
  }

  /**
   * Assert the search suggestions for a given query contain expected string.
   *
   * @param string $query
   *   The string for which to search: the 'query' is the text submitted in the
   *   autocomplete search field.
   * @param string $expected_url
   *   The 'expected' is the string that should be contained in the HTML
   *   returned in the expected suggestions (links, HTML markup, etc...).
   * @param int $expected_count
   *   (optional) An integer to check the number of expected suggestions.
   * @param bool $expected_contains
   *   (optional) A flag indicating whether the expected url should be matched
   *   exactly (strict, by default) with an end of string operator ('$=') or
   *   loosely (contains) with a contains xpath expression.
   *
   * @return void
   *   Nothing to return.
   */
  protected function assertSuggestionsContain($query, $expected_url, int $expected_count = 0, bool $expected_contains = FALSE) {
    $this->assetSuggestionsContainHelper($query, $expected_url, TRUE, $expected_count, $expected_contains);
  }

  /**
   * Assert the search suggestions for a given query do not contain a string.
   *
   * @param string $query
   *   The string for which to search: the 'query' is the text submitted in the
   *   autocomplete search field.
   * @param string $expected_url
   *   The 'expected' is the string that should not be contained in the HTML
   *   returned in the expected suggestions (links, HTML markup, etc...).
   *
   * @return void
   *   Nothing to return.
   */
  protected function assertSuggestionsNotContain($query, $expected_url) {
    // By default, the last parameter is 'FALSE' to assert not contains.
    // Note: A search query can return results which could not include the
    // expected url.
    $this->assetSuggestionsContainHelper($query, $expected_url);
  }

  /**
   * Helper function to assert search suggestions contain a string or not.
   *
   * @param string $query
   *   The string for which to search: the 'query' is the text submitted in the
   *   autocomplete search field.
   * @param string $expected_url
   *   The 'expected' is the string that should be contained in the HTML
   *   returned in the expected suggestions (links, HTML markup, etc...).
   * @param bool $assert_contains
   *   Set to 'FALSE', by default, to assert suggestions are *not* contained,
   *   or set to 'TRUE' to assert suggestions are contained with an exact match.
   * @param int $expected_count
   *   (optional) An integer to check the number of expected suggestions.
   * @param bool $expected_contains
   *   (optional) A flag indicating whether the expected url should be matched
   *   exactly (strict, by default) with an end of string operator ('$=') or
   *   loosely (contains) with a contains xpath expression.
   *
   * @return void
   *   Nothing to return.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function assetSuggestionsContainHelper(string $query, string $expected_url, bool $assert_contains = FALSE, int $expected_count = 0, bool $expected_contains = FALSE) {
    /** @var \Behat\Mink\Session $test_session */
    $test_session = $this->getSession();

    // Reset the search field to ensure no previous search results are visible.
    $this->resetSearch();
    $page = $test_session->getPage();

    // Fill in the search input field with the query and trigger a search.
    $suggestions = $page->find('css', 'ul.ui-autocomplete');
    $page->fillField(AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_field_input'], $query);
    // Add a space character by pressing the key down to trigger the search.
    $test_session->getDriver()->keyDown('//input[@id="' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_field_input'] . '"]', ' ');

    // Wait for the suggestions to be visible and continue.
    $page->waitFor(3, function () use ($suggestions) {
      // If the search is empty, the list of suggestions should not be visible.
      return $suggestions->isVisible() === TRUE;
    });

    // Assert contains exact match, otherwise, assert not contains.
    if ($assert_contains && !$expected_contains) {
      // A CSS selector has to be used here, since xpath 1.0 does not support
      // 'ends-with'. Use the ends with CSS selector '$=' to allow absolute URLs
      // to be used as well and ensure the expected URL is an exact match.
      $exact_match_count = count($suggestions->findAll('css', 'a[href$="' . $expected_url . '"]'));
    }
    else {
      // Try to be more flexible by searching links containing the expected
      // value that should not be found in the results.
      $exact_match_count = count($suggestions->findAll('xpath', '//a[contains(@href, "' . $expected_url . '")]'));
    }
    // By default: assert contains, otherwise, assert not contains.
    $this->assertEquals($assert_contains, ($exact_match_count > 0));

    // Assert the number of expected suggestions.
    if ($expected_count > 0) {
      // Count the number of 'a' tags in the suggestions list.
      $suggestions_count = count($suggestions->findAll('xpath', '//a'));
      // Compare the number of suggestions with the expected count.
      $this->assertEquals($expected_count, $suggestions_count);
    }

  }

  /**
   * Search for an empty string to clear out the autocomplete suggestions.
   *
   * @return void
   *   Nothing to return.
   */
  protected function resetSearch() {
    $page = $this->getSession()->getPage();
    // Empty out the suggestions.
    $page->fillField(AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_field_input'], '');
    // Add a space character by pressing the key down to trigger the search.
    $this->getSession()->getDriver()->keyDown('//input[@id="' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_field_input'] . '"]', ' ');
    $page->waitFor(3, function ($element) {
      // If the search is empty, the list of suggestions should not be visible.
      return ($element->find('css', 'ul.ui-autocomplete')->isVisible() === FALSE);
    });
  }

}
