<?php

namespace Drupal\Tests\page_manager\Functional;

use Drupal\page_manager\Entity\Page;
use Drupal\page_manager\Entity\PageVariant;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that the menu entries configured on a page are rendered.
 *
 * @group page_manager
 */
class PageMenuTest extends BrowserTestBase {

  use PageTestHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['page_manager', 'block'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalPlaceBlock('system_menu_block:main');
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalLogin($this->drupalCreateUser(['administer pages', 'access administration pages']));
  }

  /**
   * Tests that a page placed in a menu is rendered as a menu link.
   */
  public function testNormalMenuEntry() {
    $this->createPage('/page-manager-menu-link', 'normal_menu', [
      'title' => 'A page manager link',
      'menu_name' => 'main',
      'weight' => 0,
    ]);

    $this->drupalGet('<front>');
    $this->assertSession()->linkExists('A page manager link');
    $this->clickLink('A page manager link');
    $this->assertSession()->addressEquals('/page-manager-menu-link');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that a page configured as a tab is rendered as a local task.
   */
  public function testMenuTabEntry() {
    // The tab sits alongside the existing tabs of the user page.
    $this->createPage('/user/page-manager-tab', 'menu_tab', [
      'title' => 'A page manager tab',
      'weight' => 10,
    ]);

    $this->drupalGet('/user/page-manager-tab');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('A page manager tab');
  }

  /**
   * Tests that a page configured as an action is rendered as a local action.
   */
  public function testLocalActionEntry() {
    // A local action needs a parent path that resolves to a route of its own.
    $this->createPage('/page-manager-parent', 'none', [], 'menu_parent');
    $this->createPage('/page-manager-parent/action', 'menu_action', [
      'title' => 'A page manager action',
      'weight' => 0,
    ]);

    // The action is rendered on the parent of the path it points at.
    $this->drupalGet('/page-manager-parent');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('A page manager action');
  }

  /**
   * Tests that removing the menu entry removes the link again.
   */
  public function testRemovingTheMenuEntry() {
    $page = $this->createPage('/page-manager-menu-link', 'normal_menu', [
      'title' => 'A page manager link',
      'menu_name' => 'main',
      'weight' => 0,
    ]);

    $this->drupalGet('<front>');
    $this->assertSession()->linkExists('A page manager link');

    $page->set('menu_type', 'none');
    $page->set('menu_settings', []);
    $page->save();
    $this->triggerRouterRebuild();

    $this->drupalGet('<front>');
    $this->assertSession()->linkNotExists('A page manager link');
    // The page itself is still reachable, only the menu entry is gone.
    $this->drupalGet('/page-manager-menu-link');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Creates a renderable page with a menu entry.
   *
   * @param string $path
   *   The page path.
   * @param string $menu_type
   *   The menu type plugin ID.
   * @param array $menu_settings
   *   The menu type plugin configuration.
   * @param string $id
   *   The page ID.
   *
   * @return \Drupal\page_manager\PageInterface
   *   The saved page.
   */
  protected function createPage($path, $menu_type, array $menu_settings, $id = 'menu_test') {
    $page = Page::create([
      'id' => $id,
      'label' => 'Menu test',
      'path' => $path,
      'menu_type' => $menu_type,
      'menu_settings' => $menu_settings,
    ]);
    $page->save();

    $variant = PageVariant::create([
      'id' => $id . '_variant',
      'label' => 'Menu test variant',
      'variant' => 'http_status_code',
      'page' => $id,
    ]);
    $variant->getVariantPlugin()->setConfiguration(['status_code' => 200]);
    $variant->save();

    $this->triggerRouterRebuild();

    return $page;
  }

}
