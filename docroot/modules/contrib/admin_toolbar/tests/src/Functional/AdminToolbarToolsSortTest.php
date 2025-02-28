<?php

namespace Drupal\Tests\admin_toolbar\Functional;

use Drupal\media\Entity\MediaType;
use Drupal\system\Entity\Menu;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests Admin Toolbar tools functionality.
 *
 * @group admin_toolbar
 */
class AdminToolbarToolsSortTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'toolbar',
    'breakpoint',
    'admin_toolbar',
    'admin_toolbar_tools',
    'menu_ui',
    'media',
    'field_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A test user with permission to access the administrative toolbar.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Tests that menu updates on entity add/update/delete.
   */
  public function testMenuUpdate() {

    // Create and log in an administrative user.
    $this->adminUser = $this->drupalCreateUser([
      'access toolbar',
      'access administration pages',
      'administer site configuration',
      'administer menu',
      'access media overview',
      'administer media',
      'administer media fields',
      'administer media form display',
      'administer media display',
      'administer media types',
    ]);
    $this->drupalLogin($this->adminUser);

    $menu = Menu::create([
      'id' => 'armadillo',
      'label' => 'Armadillo',
    ]);
    $menu->save();

    $this->container->get('plugin.manager.menu.link')->rebuild();
    $this->drupalGet('/admin');

    // Assert that special menu items are present in the HTML.
    $this->assertSession()->responseContains('class="toolbar-icon toolbar-icon-admin-toolbar-tools-flush"');

    // Assert that adding a media type adds it to the admin toolbar.
    $chinchilla_media_type = MediaType::create([
      'id' => 'chinchilla',
      'label' => 'Chinchilla',
      'source' => 'image',
    ]);
    $chinchilla_media_type->save();
    $this->drupalGet('/admin');
    $this->assertMenuHasHref('/admin/structure/media/manage/chinchilla');

    // Assert that adding a menu adds it to the admin toolbar.
    $menu = Menu::create([
      'id' => 'chupacabra',
      'label' => 'Chupacabra',
    ]);
    $menu->save();
    $this->drupalGet('/admin');
    $this->assertMenuHasHref('/admin/structure/menu/manage/chupacabra');

    // Assert that deleting a menu removes it from the admin toolbar.
    $this->assertMenuHasHref('/admin/structure/menu/manage/armadillo');
    $menu = Menu::load('armadillo');
    $menu->delete();
    $this->drupalGet('/admin');
    $this->assertMenuDoesNotHaveHref('/admin/structure/menu/manage/armadillo');

    // Assert that deleting a content entity bundle removes it from admin menu.
    $this->assertMenuHasHref('/admin/structure/media/manage/chinchilla');
    $chinchilla_media_type = MediaType::load('chinchilla');
    $chinchilla_media_type->delete();
    $this->drupalGet('/admin');
    $this->assertMenuDoesNotHaveHref('/admin/structure/media/manage/chinchilla');
  }

  /**
   * Tests sorting of menus by label rather than machine name.
   */
  public function testMenuSorting() {

    // Create and log in an administrative user.
    $this->adminUser = $this->drupalCreateUser([
      'access toolbar',
      'access administration pages',
      'administer site configuration',
      'administer menu',
    ]);

    $menus = [
      'aaa' => 'lll',
      'bbb' => 'kkk',
      'ccc' => 'jjj',
      'ddd' => 'iii',
      'eee' => 'hhh',
      'fff' => 'ggg',
      'ggg' => 'fff',
      'hhh' => 'eee',
      'iii' => 'ddd',
      'jjj' => 'ccc',
      'kkk' => 'bbb',
      'lll' => 'aaa',
    ];

    foreach ($menus as $machine_name => $label) {
      $menu = Menu::create([
        'id' => $machine_name,
        'label' => $label,
      ]);
      $menu->save();
    }

    $this->drupalLogin($this->adminUser);

    $this->container->get('plugin.manager.menu.link')->rebuild();
    $this->drupalGet('/admin');

    $results = $this->getSession()->getPage()->findAll('xpath', '//a[contains(@href, "/admin/structure/menu/manage")]');

    $links = [];
    foreach ($results as $result) {
      $links[] = $result->getAttribute('href');
    }

    $expected = [
      0 => '/admin/structure/menu/manage/lll',
      1 => '/admin/structure/menu/manage/lll/add',
      2 => '/admin/structure/menu/manage/lll/delete',
      3 => '/admin/structure/menu/manage/admin',
      4 => '/admin/structure/menu/manage/admin/add',
      5 => '/admin/structure/menu/manage/kkk',
      6 => '/admin/structure/menu/manage/kkk/add',
      7 => '/admin/structure/menu/manage/kkk/delete',
      8 => '/admin/structure/menu/manage/jjj',
      9 => '/admin/structure/menu/manage/jjj/add',
      10 => '/admin/structure/menu/manage/jjj/delete',
      11 => '/admin/structure/menu/manage/iii',
      12 => '/admin/structure/menu/manage/iii/add',
      13 => '/admin/structure/menu/manage/iii/delete',
      14 => '/admin/structure/menu/manage/hhh',
      15 => '/admin/structure/menu/manage/hhh/add',
      16 => '/admin/structure/menu/manage/hhh/delete',
      17 => '/admin/structure/menu/manage/ggg',
      18 => '/admin/structure/menu/manage/ggg/add',
      19 => '/admin/structure/menu/manage/ggg/delete',
      20 => '/admin/structure/menu/manage/footer',
      21 => '/admin/structure/menu/manage/footer/add',
      22 => '/admin/structure/menu/manage/fff',
      23 => '/admin/structure/menu/manage/fff/add',
      24 => '/admin/structure/menu/manage/fff/delete',
      25 => '/admin/structure/menu/manage/eee',
      26 => '/admin/structure/menu/manage/eee/add',
      27 => '/admin/structure/menu/manage/eee/delete',
    ];

    foreach ($links as $key => $link) {
      // Using assert contains because prefaces the urls with "/subdirectory".
      $this->assertContains($expected[$key], $link);
    }
  }

  /**
   * Checks that there is a link with the specified url in the admin toolbar.
   *
   * @param string $url
   *   The url to assert exists in the admin menu.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function assertMenuHasHref($url) {
    $this->assertSession()
      ->elementExists('xpath', '//div[@id="toolbar-item-administration-tray"]//a[contains(@href, "' . $url . '")]');
  }

  /**
   * Checks that there is no link with the specified url in the admin toolbar.
   *
   * @param string $url
   *   The url to assert exists in the admin menu.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function assertMenuDoesNotHaveHref($url) {
    $this->assertSession()
      ->elementNotExists('xpath', '//div[@id="toolbar-item-administration-tray"]//a[contains(@href, "' . $url . '")]');
  }

}
