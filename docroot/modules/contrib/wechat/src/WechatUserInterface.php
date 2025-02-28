<?php

/**
 * @file
 * Contains \Drupal\wechat\WechatUserInterface.
 */

namespace Drupal\wechat;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a wechat user entity.
 */
interface WechatUserInterface extends ContentEntityInterface {

  /**
   * Gets the openid.
   *
   * @return string
   *   openid of the wechat user.
   */
  public function getOpenid();

  /**
   * Sets the wechat user openid.
   *
   * @param string $openid
   *   The openid.
   *
   * @return \Drupal\wechat\WechatUserInterface
   *   The called wechat user entity.
   */
  public function setOpenid($openid);   

}
