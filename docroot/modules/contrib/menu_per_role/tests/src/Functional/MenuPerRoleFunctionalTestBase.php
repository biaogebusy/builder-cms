<?php

declare(strict_types = 1);

namespace Drupal\Tests\menu_per_role\Functional;

use Drupal\system\Entity\Menu;
use Drupal\Tests\BrowserTestBase;

/**
 * Base class for Menu Per Role tests.
 *
 * @group menu_per_role
 */
abstract class MenuPerRoleFunctionalTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'menu_ui',
    'menu_link_content',
    'menu_per_role',
  ];

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->configFactory = $this->container->get('config.factory');

    $this->createMenu('menu1');
    $this->drupalPlaceBlock('system_menu_block:menu1', ['region' => 'header']);
  }

  /**
   * Helper method to create a Drupal menu.
   *
   * @param string $menuId
   *   The menu machine name.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createMenu(string $menuId): void {
    $menu = Menu::create([
      'id' => $menuId,
      'label' => $this->randomMachineName(16),
    ]);
    $menu->save();
  }

  /**
   * Helper method to create or update a menu link.
   *
   * @param string $menuLinkTitle
   *   The menu link title.
   * @param array $showMenuRoles
   *   The roles which can see menu link.
   * @param array $hideMenuRoles
   *   The roles which can't see menu link.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createOrUpdateMenuLink(string $menuLinkTitle, array $showMenuRoles, array $hideMenuRoles): void {
    $menu_link_storage = $this->entityTypeManager->getStorage('menu_link_content');

    /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $existing_menu_links */
    $existing_menu_links = $menu_link_storage->loadByProperties(['title' => $menuLinkTitle]);

    if (empty($existing_menu_links)) {
      $menuLink = $menu_link_storage->create([
        'title' => $menuLinkTitle,
        'link' => ['uri' => 'internal:/'],
        'menu_name' => 'menu1',
        'menu_per_role__show_role' => $showMenuRoles,
        'menu_per_role__hide_role' => $hideMenuRoles,
      ]);
    }
    else {
      $menuLink = \array_shift($existing_menu_links);
      $menuLink->set('menu_per_role__show_role', $showMenuRoles);
      $menuLink->set('menu_per_role__hide_role', $hideMenuRoles);
    }

    $menuLink->save();
  }

}
