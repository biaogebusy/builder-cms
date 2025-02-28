<?php

/**
 * @file
 * Contains \Drupal\wechat\WechatResponseMessageTypeInterface.
 */

namespace Drupal\wechat;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a wechat response message type entity.
 */
interface WechatResponseMessageTypeInterface extends ConfigEntityInterface {

  /**
   * Returns the description of the response message type.
   *
   * @return string
   *   The description of the type of this response message.
   */
  public function getDescription();

}
