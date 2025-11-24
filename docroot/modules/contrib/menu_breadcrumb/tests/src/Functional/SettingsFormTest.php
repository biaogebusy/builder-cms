<?php

namespace Drupal\Tests\menu_breadcrumb\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\RandomGeneratorTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Test Settings form for the Menu Breadcrumb module.
 *
 * @group menu_breadcrumb
 */
class SettingsFormTest extends BrowserTestBase {

  use UserCreationTrait;
  use RandomGeneratorTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['menu_breadcrumb'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with no special permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $regularUser;

  /**
   * Permissions required to administer this module.
   *
   * @var string[]
   */
  protected static $adminPermissions = [
    'administer site configuration',
  ];

  /**
   * A user with permissions defined in $this::adminPermissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Perform initial setup tasks that run before every test method.
   */
  public function setUp() : void {
    parent::setUp();
    $this->regularUser = $this->createUser([]);
    $this->adminUser = $this->createUser($this::$adminPermissions);
  }

  /**
   * Tests access without permission.
   */
  public function testSettingsPathAccessDeniedAnonymous() {
    $this->drupalGet(Url::fromRoute('menu_breadcrumb.settings'));
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests access without permission.
   */
  public function testSettingsPathAccessDeniedUnprivileged() {
    $this->drupalLogin($this->regularUser);
    $this->drupalGet(Url::fromRoute('menu_breadcrumb.settings'));
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests settings form access and functionality.
   */
  public function testSettingsForm() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet(Url::fromRoute('menu_breadcrumb.settings'));

    // Test that access is granted to the form by the permission.
    $this->assertSession()->statusCodeEquals(200);

    // Test form elements exist, and state matches configured values.
    $mapFieldsToConfig = [
      'determine_menu' => 'determine_menu',
      'disable_admin_page' => 'disable_admin_page',
      'append_current_page' => 'append_current_page',
      'current_page_as_link' => 'current_page_as_link',
      'stop_on_first_match' => 'stop_on_first_match',
      'append_member_page' => 'append_member_page',
      'member_page_as_link' => 'member_page_as_link',
      'remove_home' => 'remove_home',
      'add_home' => 'add_home',
      'front_title' => 'front_title',
      'exclude_empty_url' => 'exclude_empty_url',
      'exclude_disabled_menu_items' => 'exclude_disabled_menu_items',
      'derived_active_trail' => 'derived_active_trail',
    ];
    $config = $this->config('menu_breadcrumb.settings');
    foreach ($mapFieldsToConfig as $key => $value) {
      $this->assertSession()->fieldValueEquals(
        $key,
        $config->get($value),
      );
    }

    $page = $this->getSession()->getPage();

    // Specify a configuration, then ensure it's saved.
    $page->checkField('disable_admin_page');
    $page->checkField('append_current_page');
    $page->checkField('current_page_as_link');
    $page->checkField('stop_on_first_match');
    $page->checkField('append_member_page');
    $page->checkField('member_page_as_link');
    $page->checkField('remove_home');
    $page->checkField('add_home');
    $page->checkField('exclude_empty_url');
    $page->checkField('exclude_disabled_menu_items');
    $page->checkField('derived_active_trail');
    $page->selectFieldOption('front_title', 1);
    $page->pressButton('Save configuration');
    $this->assertSession()->checkboxChecked('disable_admin_page');
    $this->assertSession()->checkboxChecked('append_current_page');
    $this->assertSession()->checkboxChecked('current_page_as_link');
    $this->assertSession()->checkboxChecked('stop_on_first_match');
    $this->assertSession()->checkboxChecked('append_member_page');
    $this->assertSession()->checkboxChecked('member_page_as_link');
    $this->assertSession()->checkboxChecked('remove_home');
    $this->assertSession()->checkboxChecked('add_home');
    $this->assertSession()->checkboxChecked('exclude_empty_url');
    $this->assertSession()->checkboxChecked('exclude_disabled_menu_items');
    $this->assertSession()->checkboxChecked('derived_active_trail');
    $this->assertSession()->fieldValueEquals('front_title', 1);

    // Specify a configuration then ensure it's saved.
    $page->checkField('disable_admin_page');
    $page->checkField('append_current_page');
    $page->checkField('current_page_as_link');
    $page->checkField('stop_on_first_match');
    $page->checkField('append_member_page');
    $page->checkField('member_page_as_link');
    $page->checkField('remove_home');
    $page->checkField('add_home');
    $page->checkField('exclude_empty_url');
    $page->checkField('exclude_disabled_menu_items');
    $page->checkField('derived_active_trail');
    $page->selectFieldOption('front_title', 1);
    $page->pressButton('Save configuration');
    $this->assertSession()->checkboxChecked('disable_admin_page');
    $this->assertSession()->checkboxChecked('append_current_page');
    $this->assertSession()->checkboxChecked('current_page_as_link');
    $this->assertSession()->checkboxChecked('stop_on_first_match');
    $this->assertSession()->checkboxChecked('append_member_page');
    $this->assertSession()->checkboxChecked('member_page_as_link');
    $this->assertSession()->checkboxChecked('remove_home');
    $this->assertSession()->checkboxChecked('add_home');
    $this->assertSession()->checkboxChecked('exclude_empty_url');
    $this->assertSession()->checkboxChecked('exclude_disabled_menu_items');
    $this->assertSession()->checkboxChecked('derived_active_trail');
    $this->assertSession()->fieldValueEquals('front_title', 1);

    // Specify a configuration, then ensure it's saved.
    $page->checkField('disable_admin_page');
    $page->checkField('append_current_page');
    $page->checkField('current_page_as_link');
    $page->checkField('stop_on_first_match');
    $page->checkField('append_member_page');
    $page->checkField('member_page_as_link');
    $page->checkField('remove_home');
    $page->checkField('add_home');
    $page->checkField('exclude_empty_url');
    $page->checkField('exclude_disabled_menu_items');
    $page->checkField('derived_active_trail');
    $page->selectFieldOption('front_title', 1);
    $page->pressButton('Save configuration');
    $this->assertSession()->checkboxChecked('disable_admin_page');
    $this->assertSession()->checkboxChecked('append_current_page');
    $this->assertSession()->checkboxChecked('current_page_as_link');
    $this->assertSession()->checkboxChecked('stop_on_first_match');
    $this->assertSession()->checkboxChecked('append_member_page');
    $this->assertSession()->checkboxChecked('member_page_as_link');
    $this->assertSession()->checkboxChecked('remove_home');
    $this->assertSession()->checkboxChecked('add_home');
    $this->assertSession()->checkboxChecked('exclude_empty_url');
    $this->assertSession()->checkboxChecked('exclude_disabled_menu_items');
    $this->assertSession()->checkboxChecked('derived_active_trail');
    $this->assertSession()->fieldValueEquals('front_title', 1);

    // Specify another configuration then ensure it's saved.
    $page->unCheckField('disable_admin_page');
    $page->unCheckField('append_current_page');
    $page->unCheckField('current_page_as_link');
    $page->unCheckField('stop_on_first_match');
    $page->unCheckField('append_member_page');
    $page->unCheckField('member_page_as_link');
    $page->unCheckField('remove_home');
    $page->unCheckField('add_home');
    $page->unCheckField('exclude_empty_url');
    $page->unCheckField('exclude_disabled_menu_items');
    $page->unCheckField('derived_active_trail');
    $page->selectFieldOption('front_title', 0);
    $page->pressButton('Save configuration');
    $this->assertSession()->checkboxNotChecked('disable_admin_page');
    $this->assertSession()->checkboxNotChecked('append_current_page');
    $this->assertSession()->checkboxNotChecked('current_page_as_link');
    $this->assertSession()->checkboxNotChecked('stop_on_first_match');
    $this->assertSession()->checkboxNotChecked('append_member_page');
    $this->assertSession()->checkboxNotChecked('member_page_as_link');
    $this->assertSession()->checkboxNotChecked('remove_home');
    $this->assertSession()->checkboxNotChecked('add_home');
    $this->assertSession()->checkboxNotChecked('exclude_empty_url');
    $this->assertSession()->checkboxNotChecked('exclude_disabled_menu_items');
    $this->assertSession()->checkboxNotChecked('derived_active_trail');
    $this->assertSession()->fieldValueEquals('front_title', 0);
  }

}
