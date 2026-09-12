<?php

namespace Drupal\menu_item_extras\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\menu_item_extras\Service\MenuLinkContentServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Provides removing extra data action.
   */
  public function removeExtraData() {
    $this->menuLinkContentHelper->clearMenuData('all');
    $this->messenger()->addStatus($this->t('Extra data for all menus were deleted.'), 'status');
    return $this->redirect('system.modules_uninstall');
  }

}
