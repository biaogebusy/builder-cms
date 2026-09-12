<?php

declare(strict_types=1);

namespace Drupal\Tests\admin_toolbar_tools\Functional;

use Drupal\Tests\admin_toolbar\Traits\AdminToolbarHelperTestTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Test the Admin Toolbar Tools integration with the Project Browser module.
 *
 * Install the Project Browser module and verify that the Admin Toolbar Tools
 * links are correctly displayed in the admin toolbar, in the expected order.
 *
 * @see \Drupal\admin_toolbar_tools\Plugin\Derivative\ExtraLinks
 * @see admin_toolbar/admin_toolbar_tools/admin_toolbar_tools.module
 *
 * @group admin_toolbar
 * @group admin_toolbar_tools
 */
class AdminToolbarToolsProjectBrowserTest extends BrowserTestBase {

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
  ];

  /**
   * A user with access to the Admin Toolbar Tools admin menu links.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   *
   * Conditionally install the Project Browser module based on whether a
   * compatible version could be found by the composer job, since versions below
   * 2.1.0 are not supported anymore.
   * Create an admin user with the required permissions to access the Project
   * Browser routes.
   *
   * @see admin_toolbar_tools_form_project_browser_settings_alter()
   * @see composer.json
   */
  protected function setUp(): void {
    parent::setUp();

    /* Custom configuration for Project Browser */

    // Skip the test if the Project Browser module does not exist, because no
    // compatible version was found.
    if (!\Drupal::service('extension.list.module')->exists('project_browser')) {
      $this->markTestSkipped('The Project Browser module does not exist in the file system.');
    }
    // Install the Project Browser module to test the integration.
    \Drupal::service('module_installer')->install(['project_browser']);

    /* Create an admin user */

    $permissions = [
      'access toolbar',
      // Required permission to access Project Browser routes under 'Extend'.
      'administer modules',
    ];
    $this->adminUser = $this->drupalCreateUser($permissions);
  }

  /**
   * Test Admin Toolbar Tools adds Project Browser links in the expected order.
   *
   * Reverse the default installation order of Project Browser sources, saved in
   * its configuration and verify that the admin toolbar links order is updated
   * accordingly after a cache rebuild.
   * Check the 'Extend' and 'Uninstall module' links are displayed in the
   * expected order, as well.
   *
   * @see admin_toolbar_tools_form_project_browser_settings_alter()
   */
  public function testAdminToolbarToolsProjectBrowserExtraLinks(): void {

    // Get default enabled project browser sources and reverse their order.
    $enabled_sources = \Drupal::config('project_browser.admin_settings')->get('enabled_sources');
    $reverse_array = array_reverse($enabled_sources);
    // Save the reversed array as the new enabled sources config.
    \Drupal::configFactory()->getEditable('project_browser.admin_settings')
      ->set('enabled_sources', $reverse_array)
      ->save();

    // Rebuild menu items: Admin Toolbar Tools requires a cache rebuild to
    // update the toolbar menu with the new config setting. See hook form alter
    // in admin_toolbar_tools.module.
    drupal_flush_all_caches();

    // Log in as an admin user to test the admin toolbar links under 'Extend'.
    $this->drupalLogin($this->adminUser);

    // Verify that the 'Extend' link exists and is displayed second after the
    // 'Tools' link added by default by Admin Toolbar Tools.
    $this->assertAdminToolbarMenuLinkExists('admin/modules', 'Extend', 2, 'toolbar-icon-system-modules-list');
    // Verify that the Project Browser links exist in the expected order:
    // They should be reversed compared to the default order: Recipes first.
    $this->assertAdminToolbarMenuLinkExists('admin/modules/browse/recipes', 'Browse Recipes', 1);
    $this->assertAdminToolbarMenuLinkExists('admin/modules/browse/drupalorg_jsonapi', 'Browse Contrib modules', 2);
    $this->assertAdminToolbarMenuLinkExists('admin/modules/uninstall', 'Uninstall module', 3);
  }

}
