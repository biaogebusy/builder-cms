<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_toolbar_tools\Functional;

use Drupal\admin_toolbar_tools\Constants\AdminToolbarToolsConstants;
use Drupal\Tests\admin_toolbar\Traits\AdminToolbarHelperTestTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Test the Admin Toolbar Tools extra links custom links.
 *
 * Additional links provided by Admin Toolbar Tools module's 'ExtraLinks' class
 * that could not be tested in the 'AdminToolbarToolsExtraLinksTest' class, such
 * as those related with the 'config', 'field_ui' or 'language' modules.
 *
 * @see \Drupal\admin_toolbar_tools\Plugin\Derivative\ExtraLinks
 * @see \Drupal\Tests\admin_toolbar_tools\Functional\AdminToolbarToolsExtraLinksTest
 *
 * @group admin_toolbar
 * @group admin_toolbar_tools
 */
class AdminToolbarToolsExtraLinksCustomTest extends BrowserTestBase {

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
    // Modules required for the test.
    'config',
    'field_ui',
    'language',
  ];

  /**
   * A user with the necessary permissions to access the links to be tested.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set the default CSS class used for testing links in the admin toolbar.
    $this->testAdminToolbarDefaultLinkCssClass = AdminToolbarToolsConstants::ADMIN_TOOLBAR_TOOLS_EXTRA_LINKS_TEST_CSS_CLASSES;

    /* Setup users for the tests. */

    // Permissions to access the toolbar and site's configuration admin pages.
    $permissions = [
      'access toolbar',
      'access administration pages',
      'administer site configuration',
      // Test display modes links.
      'administer display modes',
      // Test languages links.
      'administer languages',
      // Test themes links.
      'administer themes',
      // Test configuration management links.
      'export configuration',
      'import configuration',
      'synchronize configuration',
      // Test user displays and fields.
      'administer account settings',
      'administer user display',
      'administer user fields',
      'administer user form display',
    ];
    // Create in an administrative user.
    $this->adminUser = $this->drupalCreateUser($permissions);
  }

  /**
   * Test Admin Toolbar Tools custom extra links.
   *
   * Login as an admin user and check the custom extra links related with:
   * - Module 'field_ui': Display modes links.
   * - Themes: Theme settings and individual themes.
   * - Module 'language': Language links, add language and detection.
   * - Module 'config': Import and Export links.
   * - Module 'user': Account settings links: user fields and displays.
   *   Additional check for the 'Logout' link with a token in the Tools menu.
   *
   * @see \Drupal\admin_toolbar_tools\Plugin\Derivative\ExtraLinks::getDerivativeDefinitions()
   */
  public function testAdminToolbarToolsExtraLinksCustom(): void {
    // Log in as an admin user to test admin pages.
    $this->drupalLogin($this->adminUser);

    // Test the custom extra links provided by the module that could not be
    // tested in the main 'AdminToolbarToolsExtraLinksTest' class.
    $custom_extra_links = [
      // Test integration with 'field_ui' display and form modes: Check all the
      // links displayed under 'Structure' > 'Display modes'.
      [
        'url' => 'admin/structure/display-modes',
        'text' => 'Display modes',
        'css_classes' => 'toolbar-icon toolbar-icon-field-ui-display-mode',
      ],
      [
        'url' => 'admin/structure/display-modes/form',
        'text' => 'Form modes',
        'position' => 1,
        'css_classes' => 'toolbar-icon toolbar-icon-entity-entity-form-mode-collection',
      ],
      [
        'url' => 'admin/structure/display-modes/view',
        'text' => 'View modes',
        'position' => 2,
        'css_classes' => 'toolbar-icon toolbar-icon-entity-entity-view-mode-collection',
      ],
      [
        'url' => 'admin/structure/display-modes/form/add',
        'text' => 'Add form mode',
      ],
      [
        'url' => 'admin/structure/display-modes/view/add',
        'text' => 'Add view mode',
      ],
      // Themes: Check all the links displayed under 'Appearance'.
      [
        'url' => 'admin/appearance/settings',
        'text' => 'Settings',
        'position' => 1,
      ],
      [
        'url' => 'admin/appearance/settings/stark',
        'text' => 'Stark',
        'position' => 1,
      ],
      // Test integration with 'language': Check the links added by the module
      // that should be under 'Configuration' > 'Regional' > 'Languages'.
      [
        'url' => 'admin/config/regional/language/add',
        'text' => 'Add language',
        'position' => 1,
      ],
      [
        'url' => 'admin/config/regional/language/detection',
        'text' => 'Detection and selection',
        'position' => 2,
      ],
      // Test integration with 'config': 'Import' and 'Export' links.
      [
        'url' => 'admin/config/development/configuration/full/import',
        'text' => 'Import',
        'position' => 1,
      ],
      [
        'url' => 'admin/config/development/configuration/full/export',
        'text' => 'Export',
        'position' => 2,
      ],
      // Test integration with 'user' entity admin pages: Check all the links
      // displayed under 'Configuration' > 'People' > 'Accounts settings'.
      [
        'url' => 'admin/config/people/accounts',
        'text' => 'Account settings',
        'position' => 1,
        'css_classes' => 'toolbar-icon toolbar-icon-entity-user-admin-form',
      ],
      [
        'url' => 'admin/config/people/accounts/fields',
        'text' => 'Manage fields',
        'position' => 1,
      ],
      [
        'url' => 'admin/config/people/accounts/form-display',
        'text' => 'Manage form display',
        'position' => 2,
      ],
      [
        'url' => 'admin/config/people/accounts/display',
        'text' => 'Manage display',
        'position' => 3,
      ],
    ];

    // Check all the custom extra links are found in the admin toolbar menu with
    // expected text, URL, position and CSS classes.
    foreach ($custom_extra_links as $link) {
      $this->assertAdminToolbarMenuLinkExists($link['url'], $link['text'], $link['position'] ?? 0, $link['css_classes'] ?? '');
    }

    // The user 'Logout' link contains a randomly generated token, so a contains
    // css condition '*=' has to be used instead of an 'end-with' '$='. Check
    // the link is found at the 4th position.
    $this->assertSession()
      // cSpell:ignore linksuser
      ->elementExists('css', 'div[id="' . $this->testAdminToolbarHtmlIds['admin_tray'] . '"] li:nth-child(4)[class*="menu-item"] a:contains("Logout")[href*="/user/logout"][class*="toolbar-icon-admin-toolbar-tools-extra-linksuser-logout"]');
  }

}
