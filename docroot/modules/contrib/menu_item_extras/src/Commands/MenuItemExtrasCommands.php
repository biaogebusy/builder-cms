<?php

namespace Drupal\menu_item_extras\Commands;

use Drupal\menu_item_extras\Service\MenuLinkContentServiceInterface;
use Drush\Commands\DrushCommands;

/**
 * Class for running Drush commands.
 */
class MenuItemExtrasCommands extends DrushCommands {

  /**
   * Drupal\menu_item_extras\Service\MenuLinkContentServiceInterface definition.
   *
   * @var \Drupal\menu_item_extras\Service\MenuLinkContentServiceInterface
   */
  protected $menuLinkContentService;

  /**
   * MenuItemExtrasCommands constructor.
   */
  public function __construct(MenuLinkContentServiceInterface $mie_service) {
    $this->menuLinkContentService = $mie_service;
  }

  /**
   * Clear menu related data.
   *
   * @param string $menu
   *   Menu name.
   *
   * @command menu-item-extras-clear-extra-data
   * @aliases mie:clear_data
   * @usage mie:clear_data "main"
   *   Clear extra data for the Main menu.
   * @usage mie:clear_data all
   *   Clear extra data for all menus.
   */
  public function clearExtraData($menu) {
    $this->menuLinkContentService->clearMenuData($menu);
    if ($menu === 'all') {
      $this->output()->writeln('Extra data for all menus were deleted.');
    }
    else {
      $this->output()->writeln("Extra data for the '{$menu}' menus were deleted.");
    }
  }

}
