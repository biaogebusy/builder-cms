<?php

/**
 * @file
 * Contains \Drupal\wechat\WechatRequestMessageTypeInterface.
 */

namespace Drupal\wechat;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a wechat request message type entity.
 */
interface WechatRequestMessageTypeInterface extends ConfigEntityInterface {

  /**
   * Returns the description of the request message type.
   *
   * @return string
   *   The description of the type of this request message.
   */
  public function getDescription();

}
