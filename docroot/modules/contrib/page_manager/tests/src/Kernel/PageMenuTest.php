<?php

namespace Drupal\Tests\page_manager\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\page_manager\Entity\Page;
use Drupal\page_manager\Entity\PageVariant;
use Drupal\page_manager\Plugin\PageManagerMenu\DefaultMenuTab;
use Drupal\page_manager\Plugin\PageManagerMenu\MenuAction;
use Drupal\page_manager\Plugin\PageManagerMenu\MenuTab;
use Drupal\page_manager\Plugin\PageManagerMenu\NormalMenu;
use Drupal\Tests\SchemaCheckTestTrait;

/**
 * Tests the menu entries a page can provide.
 *
 * @group page_manager
 */
class PageMenuTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['page_manager', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
  }

  /**
   * Provides menu types along with a representative set of settings.
   */
  public static function providerMenuSettings() {
    return [
      'no menu entry' => ['none', []],
      'normal menu entry' => [
        'normal_menu',
        [
          'title' => 'A menu link',
          'menu_name' => 'main',
          'weight' => -3,
        ],
      ],
      'menu tab' => [
        'menu_tab',
        [
          'title' => 'A tab',
          'weight' => 2,
        ],
      ],
      'local action' => [
        'menu_action',
        [
          'title' => 'An action',
          'weight' => 0,
        ],
      ],
      'default menu tab' => [
        'default_menu_tab',
        [
          'title' => 'A default tab',
          'weight' => 0,
          'parent_menu_type' => 'menu',
          'parent_title' => 'The parent',
          'parent_menu' => 'main',
          'parent_weight' => 5,
        ],
      ],
    ];
  }

  /**
   * Tests that every menu type stores settings that match the config schema.
   *
   * @dataProvider providerMenuSettings
   */
  public function testMenuSettingsConfigSchema($menu_type, array $menu_settings) {
    $page = Page::create([
      'id' => 'menu_schema_test',
      'label' => 'Menu schema test',
      'path' => '/menu-schema-test',
      'menu_type' => $menu_type,
      'menu_settings' => $menu_settings,
    ]);
    $page->save();

    $config = $this->config('page_manager.page.menu_schema_test');
    $this->assertSame($menu_type, $config->get('menu_type'));
    $this->assertEquals($menu_settings, $config->get('menu_settings'));
    $this->assertConfigSchema(\Drupal::service('config.typed'), $config->getName(), $config->get());
  }

  /**
   * Tests that the page returns the menu type plugin it is configured with.
   */
  public function testGetMenu() {
    $page = Page::create([
      'id' => 'get_menu_test',
      'path' => '/get-menu-test',
    ]);

    // A page defaults to having no menu entry.
    $this->assertSame('none', $page->getMenuType());
    $this->assertNull($page->getMenu());

    $expected = [
      'normal_menu' => NormalMenu::class,
      'menu_tab' => MenuTab::class,
      'menu_action' => MenuAction::class,
      'default_menu_tab' => DefaultMenuTab::class,
    ];
    foreach ($expected as $menu_type => $class) {
      $page->set('menu_type', $menu_type);
      $this->assertInstanceOf($class, $page->getMenu(), "Menu type $menu_type resolves to $class.");
    }

    // A menu type provided by a module that is no longer installed must not
    // fatal, it simply has no menu entry any more.
    $page->set('menu_type', 'menu_type_that_does_not_exist');
    $this->assertNull($page->getMenu());
  }

  /**
   * Tests that a page placed in a menu provides a menu link.
   */
  public function testMenuLinkDerivative() {
    $this->createPage('normal_menu', [
      'title' => 'Test menu link',
      'menu_name' => 'main',
      'weight' => -3,
    ]);

    $definitions = $this->menuLinkDefinitions();
    $this->assertArrayHasKey('page_manager_page:page_manager.menu_test', $definitions);

    $definition = $definitions['page_manager_page:page_manager.menu_test'];
    $this->assertSame('Test menu link', $definition['title']);
    $this->assertSame('main', $definition['menu_name']);
    $this->assertSame(-3, $definition['weight']);
    $this->assertSame('page_manager.page_view_menu_test_menu_test-variant', $definition['route_name']);
  }

  /**
   * Tests that a page configured as a tab provides a local task.
   */
  public function testLocalTaskDerivative() {
    $this->createPage('menu_tab', [
      'title' => 'Test tab',
      'weight' => 4,
    ]);

    $definitions = \Drupal::service('plugin.manager.menu.local_task')->getDefinitions();
    $this->assertArrayHasKey('page_manager_page:page_manager.page_tab_menu_test', $definitions);

    $definition = $definitions['page_manager_page:page_manager.page_tab_menu_test'];
    $this->assertSame('Test tab', $definition['title']);
    $this->assertEquals(4, $definition['weight']);
  }

  /**
   * Tests that a page configured as an action provides a local action.
   */
  public function testLocalActionDerivative() {
    // A local action is rendered on the parent of the page it points at, so
    // the parent path has to resolve to a route of its own.
    $this->createPage('menu_action', [
      'title' => 'Test action',
      'weight' => 1,
    ], '/user/test-action');

    $definitions = \Drupal::service('plugin.manager.menu.local_action')->getDefinitions();
    $this->assertArrayHasKey('page_manager_page:page_manager.page_action_menu_test', $definitions);

    $definition = $definitions['page_manager_page:page_manager.page_action_menu_test'];
    $this->assertSame('Test action', $definition['title']);
    $this->assertEquals(1, $definition['weight']);
    $this->assertSame(['user.page'], $definition['appears_on']);
  }

  /**
   * Tests that a default tab can additionally place its parent in a menu.
   */
  public function testDefaultTabParentMenuLink() {
    $this->createPage('default_menu_tab', [
      'title' => 'The default tab',
      'weight' => 0,
      'parent_menu_type' => 'menu',
      'parent_title' => 'The parent link',
      'parent_menu' => 'main',
      'parent_weight' => 7,
    ]);

    $definitions = $this->menuLinkDefinitions();
    $this->assertArrayHasKey('page_manager_page:page_manager.menu_test', $definitions);

    // The link describes the parent, not the tab itself.
    $definition = $definitions['page_manager_page:page_manager.menu_test'];
    $this->assertSame('The parent link', $definition['title']);
    $this->assertSame(7, $definition['weight']);

    // The tab itself is still a local task, sitting under its own parent tab.
    $tasks = \Drupal::service('plugin.manager.menu.local_task')->getDefinitions();
    $this->assertArrayHasKey('page_manager_page:page_manager.page_tab_menu_test', $tasks);
  }

  /**
   * Tests that a default tab whose parent is not a menu has no menu link.
   */
  public function testDefaultTabWithoutParentMenuLink() {
    $this->createPage('default_menu_tab', [
      'title' => 'The default tab',
      'weight' => 0,
      'parent_menu_type' => 'none',
    ]);

    $this->assertArrayNotHasKey('page_manager_page:page_manager.menu_test', $this->menuLinkDefinitions());
  }

  /**
   * Tests that changing the menu type removes the entry it used to provide.
   */
  public function testChangingTheMenuTypeRemovesTheOldEntry() {
    $page = $this->createPage('normal_menu', [
      'title' => 'Test menu link',
      'menu_name' => 'main',
      'weight' => 0,
    ]);
    $this->assertArrayHasKey('page_manager_page:page_manager.menu_test', $this->menuLinkDefinitions());

    // Switching to a tab has to take the menu link away again.
    $page->set('menu_type', 'menu_tab');
    $page->set('menu_settings', ['title' => 'Test tab', 'weight' => 0]);
    $page->save();
    $this->rebuild();

    $this->assertArrayNotHasKey('page_manager_page:page_manager.menu_test', $this->menuLinkDefinitions());
    $this->assertArrayHasKey('page_manager_page:page_manager.page_tab_menu_test', \Drupal::service('plugin.manager.menu.local_task')->getDefinitions());
  }

  /**
   * Tests that a disabled page provides no menu entry.
   */
  public function testDisabledPageHasNoMenuLink() {
    $page = $this->createPage('normal_menu', [
      'title' => 'Test menu link',
      'menu_name' => 'main',
      'weight' => 0,
    ]);
    $this->assertArrayHasKey('page_manager_page:page_manager.menu_test', $this->menuLinkDefinitions());

    $page->disable()->save();
    $this->rebuild();

    $this->assertArrayNotHasKey('page_manager_page:page_manager.menu_test', $this->menuLinkDefinitions());
  }

  /**
   * Tests that deleting a page removes the menu link it provided.
   */
  public function testDeletingPageRemovesTheMenuLink() {
    $page = $this->createPage('normal_menu', [
      'title' => 'Test menu link',
      'menu_name' => 'main',
      'weight' => 0,
    ]);
    $this->assertArrayHasKey('page_manager_page:page_manager.menu_test', $this->menuLinkDefinitions());

    $page->delete();
    $this->rebuild();

    $this->assertArrayNotHasKey('page_manager_page:page_manager.menu_test', $this->menuLinkDefinitions());
  }

  /**
   * Creates a page with a variant, so that it has a route, and rebuilds.
   *
   * @param string $menu_type
   *   The menu type plugin ID.
   * @param array $menu_settings
   *   The menu type plugin configuration.
   * @param string $path
   *   The page path.
   *
   * @return \Drupal\page_manager\PageInterface
   *   The saved page.
   */
  protected function createPage($menu_type, array $menu_settings, $path = '/menu-test') {
    $page = Page::create([
      'id' => 'menu_test',
      'label' => 'Menu test',
      'path' => $path,
      'menu_type' => $menu_type,
      'menu_settings' => $menu_settings,
    ]);
    $page->save();

    PageVariant::create([
      'id' => 'menu_test-variant',
      'variant' => 'simple_page',
      'page' => 'menu_test',
    ])->save();

    $this->rebuild();

    return $page;
  }

  /**
   * Rebuilds the router and the menu entries derived from it.
   */
  protected function rebuild() {
    \Drupal::service('router.builder')->rebuild();
    \Drupal::service('plugin.manager.menu.link')->rebuild();
    \Drupal::service('plugin.manager.menu.local_task')->clearCachedDefinitions();
    \Drupal::service('plugin.manager.menu.local_action')->clearCachedDefinitions();
  }

  /**
   * Gets the currently defined menu link plugin definitions.
   *
   * @return array
   *   The menu link definitions.
   */
  protected function menuLinkDefinitions() {
    return \Drupal::service('plugin.manager.menu.link')->getDefinitions();
  }

}
