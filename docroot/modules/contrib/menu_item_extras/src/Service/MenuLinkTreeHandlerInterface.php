<?php

namespace Drupal\menu_item_extras\Service;

use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Interface for class MenuLinkTreeHandler.
 *
 * @package Drupal\menu_item_extras\Service
 */
interface MenuLinkTreeHandlerInterface {

  /**
   * Get menu_link_content entity.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface $link
   *   Link object.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface|null
   *   Menu Link Content entity.
   */
  public function getMenuLinkItemEntity(MenuLinkInterface $link);

  /**
   * Get menu_link_content view mode.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $entity
   *   Link object.
   *
   * @return string
   *   View mode machine name.
   */
  public function getMenuLinkContentViewMode(MenuLinkContentInterface $entity);

  /**
   * Get Menu Link Content entity content.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $link
   *   Original link entity.
   *
   * @return array
   *   Renderable menu item content.
   */
  public function getMenuLinkItemContent(MenuLinkContentInterface $link);

  /**
   * Get Menu Link Item view mdoe.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface $link
   *   Original link entity.
   *
   * @return string
   *   View mode property.
   */
  public function getMenuLinkItemViewMode(MenuLinkInterface $link);

  /**
   * Checks if Menu Link Children is enabled to display.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface $link
   *   Original link entity.
   *
   * @return bool
   *   Returns TRUE is Menu Link Children is enabled in display.
   */
  public function isMenuLinkDisplayedChildren(MenuLinkInterface $link);

  /**
   * Process menu tree items. Add menu item content.
   *
   * @param array $items
   *   Menu tree items.
   * @param string $menu_name
   *   Menu name.
   * @param int $menu_level
   *   Menu level number.
   * @param bool $show_item_link
   *   Show or not item link.
   *
   * @return array
   *   Returns modified menu tree items array.
   */
  public function processMenuLinkTree(array &$items, $menu_name, $menu_level = -1, $show_item_link = FALSE);

}
