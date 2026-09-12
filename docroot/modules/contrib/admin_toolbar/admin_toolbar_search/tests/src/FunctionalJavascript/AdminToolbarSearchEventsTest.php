<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_toolbar_search\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\admin_toolbar_search\Constants\AdminToolbarSearchConstants;

/**
 * Test the Javascript events interactions with the Admin Toolbar Search.
 *
 * Ensure the javascript events attached to the module are working as expected:
 * - The search input field should be focused when the keyboard shortcut
 *   'Alt + a' is used.
 * - The search input field should be focused when the search tab is clicked.
 *
 * @see admin_toolbar_search/js/admin_toolbar_search.keyboard_shortcut.js
 *
 * @group admin_toolbar
 * @group admin_toolbar_search
 */
class AdminToolbarSearchEventsTest extends WebDriverTestBase {

  /**
   * A user with access to the Admin Toolbar Search.
   *
   * An admin user with the permissions to access the toolbar and the search
   * functionality defined by the module.
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
    'admin_toolbar_search',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    /* Setup custom configuration. */

    // Set 'display_menu_item' to TRUE to display the search input field in a
    // tray in the toolbar and not directly as a toolbar item (default).
    $this->config('admin_toolbar_search.settings')
      ->set('display_menu_item', TRUE)
      ->save();

    /* Setup users for the tests. */

    // Access to the toolbar and the admin toolbar search.
    $permissions = [
      'access toolbar',
      // This permission is needed to test the search keyboard shortcut.
      'use admin toolbar search',
    ];

    // Create an admin user with access to the admin toolbar search.
    $this->adminUser = $this->drupalCreateUser($permissions);

    // Login with an admin user with access to the search in the toolbar.
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test the search keyboard shortcut functionality.
   *
   * Ensure the search input field is focused when the keyboard shortcut
   * 'Alt + a' is used:
   *  - Call the standard test routine with no event, defaulting to 'keyboard':
   *    Trigger the keyboard shortcut 'Alt + a' to focus on the search input
   *    field.
   *
   * @return void
   *   Nothing to return.
   *
   * @see doTestAdminToolbarSearchEvents()
   */
  public function testAdminToolbarSearchKeyboardShortcut() {
    // Call the standard test routine with the default 'keyboard' event.
    $this->doTestAdminToolbarSearchEvents();
  }

  /**
   * Test the click event on the search tab functionality.
   *
   * Ensure the search input field is focused when the search tab is clicked:
   *  - Call the standard test routine with the 'click' event:
   *    Click on the search tab in the toolbar and focus the input field.
   *
   * @return void
   *   Nothing to return.
   *
   * @see doTestAdminToolbarSearchEvents()
   */
  public function testAdminToolbarSearchClickFocus() {
    // Call the standard test routine with the 'click' event.
    $this->doTestAdminToolbarSearchEvents('click');
  }

  /**
   * Sub routine to test the javascript events attached to the search field.
   *
   * Ensure the search input field is focused when the keyboard shortcut
   * 'Alt + a' is used or the search tab is clicked:
   * - Check that the search tray and input field are initially *not* visible.
   * - Trigger the specified event:
   *   - Focus, by default, with the keyboard shortcut 'Alt + a'.
   *   - Click, if specified, on the search tab in the toolbar.
   * - Check that the search tray and input field are now visible.
   * - Check that the search input field has focus.
   *
   * This test assumes the 'display_menu_item' setting is enabled, so the
   * search input field is displayed in a tray in the toolbar, thus initially
   * not visible when the page loads.
   *
   * @param string $event
   *   The event to trigger to focus the search input field:
   *   - 'keyboard' (default): Trigger the keyboard shortcut 'Alt + a'.
   *   - 'click': Click on the search tab in the toolbar.
   *
   * @return void
   *   Nothing to return.
   */
  public function doTestAdminToolbarSearchEvents(string $event = 'keyboard'): void {
    // Get the current test session.
    $test_session = $this->getSession();
    // Get the current page.
    $page = $test_session->getPage();
    // Get the search tray and input field elements.
    $search_tray_element = $page->find('css', '#' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_tray']);
    $search_input_element = $page->find('css', '#' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_input']);

    // Check the search tray and input field are initially *not* visible.
    $this->assertFalse($search_tray_element->isVisible());
    $this->assertFalse($search_input_element->isVisible());

    if ($event === 'click') {
      // Click on the search tab to open the toolbar tray and focus the input
      // field.
      $page->find('css', '#' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_tab'] . ' .toolbar-item')->click();
    }
    else {
      // Trigger the keyboard shortcut 'Alt + a' to focus the search input
      // field.
      $test_session->executeScript("document.dispatchEvent(new KeyboardEvent('keydown', { keyCode: 65, altKey: true }));");
    }

    // Check the search tray and input field are now visible.
    $this->assertTrue($search_tray_element->isVisible());
    $this->assertTrue($search_input_element->isVisible());

    // Check that the search input field *has* focus.
    $search_input_has_focus = $test_session->evaluateScript('document.activeElement.getAttribute("id") === "' . AdminToolbarSearchConstants::ADMIN_TOOLBAR_SEARCH_HTML_IDS['search_input'] . '";');
    $this->assertTrue($search_input_has_focus);
  }

}
