<?php

namespace Drupal\Tests\admin_toolbar_search\Functional;

use Drupal\block\Entity\Block;
use Drupal\Tests\BrowserTestBase;

/**
 * Test the Admin Toolbar Search settings form.
 *
 * @group admin_toolbar
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
    // The admin toolbar tools module is required to check the display of the
    // three menu local tasks (tabs) on the admin settings form page.
    'admin_toolbar_tools',
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

    $permissions = [
      'access toolbar',
      'access administration pages',
      'administer site configuration',
      // This permission is needed to test the inclusion of the JS libraries.
      'use admin toolbar search',
    ];
    $this->adminUser = $this->drupalCreateUser($permissions);
    // Create a block for testing module's primary local tasks.
    $this->createPrimaryLocalTasksBlock();
  }

  /**
   * Test backend Admin Toolbar Tools settings form fields and submission.
   */
  public function testAdminToolbarToolsSettingsForm(): void {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Log in as an admin user to test admin pages.
    $this->drupalLogin($this->adminUser);

    // Test the 'Admin Toolbar Search settings' page form submission and fields.
    $this->drupalGet('admin/config/user-interface/admin-toolbar-search');

    /* Test default values to compare with the ones after the changes. */

    // Test 'enable_keyboard_shortcut'.
    $keyboard_shortcut_js = 'admin_toolbar_search/js/admin_toolbar_search.keyboard_shortcut.js';
    // Check the keyboard shortcut library is loaded by default.
    $assert->responseContains($keyboard_shortcut_js);
    // Check the display menu item is disabled by default.
    $assert->responseContains('"displayMenuItem":false');

    // Change the value of 'display_menu_item' and submit the form.
    $this->submitForm([
      'display_menu_item' => TRUE,
    ], 'Save configuration');
    // Check the form submission was successful.
    $assert->pageTextContains('The configuration options have been saved.');

    /* Test updated values. */

    // Check the keyboard shortcut library is loaded by default.
    $assert->responseContains($keyboard_shortcut_js);
    // Check the display menu item is disabled by default.
    $assert->responseContains('"displayMenuItem":true');

    // Change the value of 'enable_keyboard_shortcut' and submit the form.
    $this->submitForm([
      'enable_keyboard_shortcut' => FALSE,
    ], 'Save configuration');
    // Check the form submission was successful.
    $assert->pageTextContains('The configuration options have been saved.');

    // Check the keyboard shortcut library is not loaded.
    $assert->responseNotContains('admin_toolbar_search.keyboard_shortcut.js');
    // Check the display menu item JS is not found.
    $assert->responseNotContains('displayMenuItem');
    // Check the module's local tasks are displayed as expected.
    $local_tasks_regex = '/<div.*-primary-local-tasks.*>([\r\n].*)+<a.*>Toolbar settings<\/a>.*[\r\n].*<a.*>Search settings<\/a>.*[\r\n].*<a.*>Tools settings<\/a>.*[\r\n].*([\r\n].*)+<\/div>/';
    $assert->responseMatches($local_tasks_regex);
  }

  /**
   * Helper function to create a block to test module's primary local tasks.
   */
  private function createPrimaryLocalTasksBlock(): void {
    $values = [
      // A unique ID for the block instance.
      'id' => 'stark_primary_local_tasks',
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
    $block = Block::create($values);
    $block->save();
  }

}
