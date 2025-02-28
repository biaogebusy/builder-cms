<?php

/**
 * @file
 * Contains \Drupal\wechat_menu\WechatMenuItemInterface.
 */

namespace Drupal\wechat_menu;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a wechat menu item entity.
 */
interface WechatMenuItemInterface extends ContentEntityInterface {


  /**
   * Gets the weight.
   *
   * @return string
   *   weight of the item.
   */
  public function getWeight();

  /**
   * Sets the weight.
   *
   * @param string $weight
   *   The weight.
   *
   * @return \Drupal\wechat_menu\WechatMenuItemInterface
   *   The called menu item entity.
   */
  public function setWeight($weight);

}
