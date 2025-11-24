<?php

namespace Drupal\Tests\admin_toolbar_tools\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test the Admin Toolbar Tools ToolbarController class.
 *
 * @group admin_toolbar
 *
 * @see \Drupal\admin_toolbar_tools\Controller\ToolbarController
 */
class AdminToolbarToolsToolbarControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'admin_toolbar_tools',
    // Required to test link '/admin/flush/views' to flush views cache.
    'views',
  ];

  /**
   * A user with access to the Admin Toolbar Tools admin menu links.
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
      // See module's routing file for the permissions needed to see the links.
      'administer site configuration',
    ];
    $this->adminUser = $this->drupalCreateUser($permissions);
  }

  /**
   * Test Admin Toolbar Tools ToolbarController, all its links and routes.
   */
  public function testAdminToolbarToolsToolbarController(): void {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();

    // Log in as an admin user to test the admin toolbar links.
    $this->drupalLogin($this->adminUser);

    // Basic Tests coverage of the Admin Toolbar Tools ToolbarController: Click
    // the link added by the module and check the expected message is displayed.
    $admin_toolbar_tools_paths = [
      '/admin/flush' => 'All caches cleared.',
      '/admin/flush/cssjs' => 'CSS and JavaScript cache cleared.',
      '/admin/flush/plugin' => 'Plugins cache cleared.',
      '/admin/flush/static-caches' => 'Static cache cleared.',
      '/admin/flush/menu' => 'Routing and links cache cleared.',
      '/admin/flush/rendercache' => 'Render cache cleared.',
      '/admin/flush/views' => 'Views cache cleared.',
      '/admin/flush/twig' => 'Twig cache cleared.',
      '/admin/flush/theme_rebuild' => 'Theme registry rebuilt.',
      '/run-cron' => 'Cron ran successfully.',
    ];
    // For each route defined by the module in the ToolbarController:
    foreach ($admin_toolbar_tools_paths as $path => $message) {
      // Click on the corresponding menu link to trigger the controller method.
      // Use a CSS attribute contains selector to cover absolute paths as well.
      $this->click('a[href*="' . $path . '"] ');

      // Check the page text contains the expected confirmation message.
      $assert->pageTextContains($message);
    }
  }

}
