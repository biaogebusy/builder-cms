<?php

namespace Drupal\menu_item_extras\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\system\MenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\menu_item_extras\Service\MenuLinkContentServiceInterface;

/**
 * Defines a route controller for a form for menu link content entity creation.
 */
class MenuController extends ControllerBase {

  /**
   * The menu link content service helper.
   *
   * @var \Drupal\menu_item_extras\Service\MenuLinkContentServiceInterface
   */
  protected $menuLinkContentHelper;

  /**
   * Constructs a new \Drupal\menu_item_extras\Controller\MenuController object.
   *
   * @param \Drupal\menu_item_extras\Service\MenuLinkContentServiceInterface $menuLinkContentHelper
   *   The menu link content service helper.
   */
  public function __construct(MenuLinkContentServiceInterface $menuLinkContentHelper) {
    $this->menuLinkContentHelper = $menuLinkContentHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('menu_item_extras.menu_link_content_helper')
    );
  }

  /**
   * Provides the menu link creation form.
   *
   * @param \Drupal\system\MenuInterface $menu
   *   An entity representing a custom menu.
   *
   * @deprecated in drupal:2.11.0 and is removed from drupal:3.0.0. https://www.drupal.org/project/drupal/issues/2923429.
   *
   * @see https://www.drupal.org/project/drupal/issues/2923429
   *
   * @return array
   *   Returns the menu link creation form.
   */
  public function addLink(MenuInterface $menu) {
    $menu_link = $this->entityTypeManager()
      ->getStorage('menu_link_content')
      ->create([
        'id' => '',
        'parent' => '',
        'menu_name' => $menu->id(),
      ]);
    return $this->entityFormBuilder()->getForm($menu_link);
  }

  /**
   * Provides removing extra data action.
   */
  public function removeExtraData() {
    $this->menuLinkContentHelper->clearMenuData('all');
    $this->messenger()->addStatus($this->t('Extra data for all menus were deleted.'), 'status');
    return $this->redirect('system.modules_uninstall');
  }

}
