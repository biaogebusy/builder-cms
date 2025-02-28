<?php

namespace Drupal\menu_item_extras\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Interface for class MenuLinkContentHelper.
 *
 * @package Drupal\menu_item_extras\Service
 */
interface MenuLinkContentServiceInterface {

  /**
   * Update menu items.
   *
   * @param string $menu_id
   *   Menu id is a bundle for menu items that required to be updated.
   * @param bool $extras_enabled
   *   Flag of enabled functionality.
   *
   * @return bool
   *   Success or failed result of update.
   */
  public function updateMenuItemsBundle($menu_id, $extras_enabled = TRUE);

  /**
   * Update menu link item.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $item
   *   Menu item that required to be updated.
   * @param bool $extras_enabled
   *   Flag of enabled functionality.
   * @param bool $save
   *   Flag of saving after update.
   */
  public function updateMenuItemBundle(MenuLinkContentInterface $item, $extras_enabled = TRUE, $save = FALSE);

  /**
   * Cleanups all field that added by entity bundle.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity for manipulating.
   */
  public function cleanupFields(ContentEntityInterface $entity);

  /**
   * Runs entity definition updates for menu_link_content entity.
   */
  public function doEntityUpdate();

  /**
   * Clears special menu or all menus extra data.
   *
   * @param string $menu_id
   *   Machine menu name for clearing.
   */
  public function clearMenuData($menu_id = 'all');

  /**
   * Runs field `bundle` updates for entity.
   */
  public function updateMenuLinkContentBundle();

}
