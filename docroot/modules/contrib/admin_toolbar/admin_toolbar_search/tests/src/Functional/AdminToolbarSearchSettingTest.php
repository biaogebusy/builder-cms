<?php

namespace Drupal\Tests\admin_toolbar_search\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Test the functionality of admin toolbar search.
 *
 * @group admin_toolbar
 * @group admin_toolbar_search
 */
class AdminToolbarSearchSettingTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'admin_toolbar_search',
    'node',
    'media',
    'field_ui',
    'menu_ui',
    'block',
  ];

  /**
   * A user with the 'Use Admin Toolbar search' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $userWithAccess;

  /**
   * A test user without the 'Use Admin Toolbar search' permission..
   *
   * @var \Drupal\user\UserInterface
   */
  protected $noAccessUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $permissions = [
      'access toolbar',
      'administer menu',
      'access administration pages',
      'administer site configuration',
      'administer content types',
    ];
    $this->noAccessUser = $this->drupalCreateUser($permissions);
    $permissions[] = 'use admin toolbar search';
    $this->userWithAccess = $this->drupalCreateUser($permissions);
  }

  /**
   * Tests search functionality without admin_toolbar_tools enabled.
   *
   * @return void
   *   Nothing to return.
   */
  public function testToolbarSearch() {
    $this->drupalLogin($this->userWithAccess);

    $this->drupalGet(Url::fromRoute('system.admin'));
    $this->assertSession()->responseNotContains('id="toolbar-item-administration-search');

    $this->config('admin_toolbar_search.settings')->set('display_menu_item', 1);
    $this->config('admin_toolbar_search.settings')->save();
    $this->drupalGet(Url::fromRoute('system.admin'));
    $this->assertSession()->responseContains('id="toolbar-item-administration-search');

    $this->config('admin_toolbar_search.settings')->set('display_menu_item', 0);
    $this->config('admin_toolbar_search.settings')->save();
    $this->drupalGet(Url::fromRoute('system.admin'));
    $this->assertSession()->responseNotContains('id="toolbar-item-administration-search');
  }

}
