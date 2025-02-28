<?php

namespace Drupal\Tests\menu_item_extras\Functional;

use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that installing and uninstalling module does not break anything.
 *
 * @group menu_item_extras
 */
class MenuItemExtrasUninstallTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * The test runner will merge the $modules lists from this class, the class
   * it extends, and so on up the class hierarchy. It is not necessary to
   * include modules in your list that a parent class has already declared.
   *
   * @var string[]
   *
   * @see \Drupal\Tests\BrowserTestBase::installDrupal()
   */
  protected static $modules = ['menu_link_content', 'link'];

  /**
   * Menu used during the test.
   *
   * @var \Drupal\Core\Entity\EntityInterface|static
   */
  protected $menu;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests module install and uninstall processes.
   */
  public function testMenuItemExtrasInstallUninstall() {
    $menu_name = 'testmenu';
    $entity_type = 'menu_link_content';
    $label = $this->randomMachineName(16);
    $this->menu = Menu::create([
      'id' => $menu_name,
      'label' => $label,
      'description' => $this->randomString(32),
    ]);
    $this->menu->save();

    $item = [
      'title' => 'Link',
      'link' => 'https://example.com',
      'enabled' => TRUE,
      'description' => 'Test Description',
      'expanded' => TRUE,
      'menu_name' => $this->menu->id(),
      'parent' => "{$this->menu->id()}:",
      'weight' => -10,
    ];
    $link = MenuLinkContent::create($item);
    $link->save();

    $module_installer = \Drupal::service('module_installer');

    $module_installer->install(['menu_item_extras']);
    $this->drupalLogin($this->rootUser);
    $url = Url::fromRoute('entity.menu_link_content.edit_form', ['menu_link_content' => $link->id()]);
    $this->drupalGet($url);
    $this->assertSame(200, $this->getSession()->getStatusCode(), 'Unexpected error on the menu item edit page.');
    $this->drupalGet($url);
    $this->submitForm([], 'Save');
    $this->assertNotSame(500, $this->getSession()->getStatusCode(), "Unexpected error on the menu item edit page.");
    $this->drupalLogout();
    FieldStorageConfig::create([
      'field_name' => 'field_test',
      'langcode' => 'en',
      'entity_type' => $entity_type,
      'type' => 'boolean',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_test',
      'entity_type' => $entity_type,
      'bundle' => $this->menu->id(),
      'label' => $label,
      'required' => FALSE,
    ])->save();
    $linkAfterInstall = MenuLinkContent::load($link->id());
    $this->assertEquals($this->menu->id(), $linkAfterInstall->get('bundle')->getString());
    $linkAfterInstall->set('field_test', 1);
    $linkAfterInstall->save();
    $this->assertEquals('1', $linkAfterInstall->get('field_test')->getString());

    $module_installer->uninstall(['menu_item_extras']);

    $linkAfterUninstall = MenuLinkContent::load($link->id());
    $this->assertEquals($entity_type, $linkAfterUninstall->get('bundle')->getString());
    $this->assertFalse($linkAfterUninstall->hasField('field_test'));

    $definishions = \Drupal::entityTypeManager()->getDefinition($entity_type);
    $this->assertNotEquals('Drupal\menu_item_extras\Entity\MenuItemExtrasMenuLinkContent', $definishions->getClass());
    $this->assertNotEquals('Drupal\menu_item_extras\MenuLinkContentViewsData', $definishions->getHandlerClass('views_data'));

    $module_installer->install(['menu_item_extras']);
    $this->drupalGet(Url::fromRoute('entity.' . $entity_type . '.edit_form', [$entity_type => $linkAfterUninstall->id()]));
    $this->assertSession()->elementNotExists('css', 'input[name="field_test[value]"]');
  }

}
