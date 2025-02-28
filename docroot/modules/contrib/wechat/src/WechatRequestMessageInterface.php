<?php

/**
 * @file
 * Contains \Drupal\wechat\WechatRequestMessageInterface.
 */

namespace Drupal\wechat;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a wechat request message entity.
 */
interface WechatRequestMessageInterface extends ContentEntityInterface {
  /**
   * Gets the wechat request message type.
   *
   * @return string
   *   The wechat request message type.
   */
  public function getMsgType();

  /**
   * Gets the MsgId.
   *
   * @return string
   *   MsgId of the request message.
   */
  public function getMsgId();

  /**
   * Sets the request message MsgId.
   *
   * @param string $msg_id
   *   The node MsgId.
   *
   * @return \Drupal\wechat\WechatRequestMessageInterface
   *   The called request message entity.
   */
  public function setMsgId($msg_id);

  /**
   * Gets the FromUserName.
   *
   * @return string
   *   FromUserName of the request message.
   */
  public function getFromUserName();

  /**
   * Sets the from_user_name.
   *
   * @param string $from_user_name
   *   The request message from_user_name.
   *
   * @return \Drupal\wechat\WechatRequestMessageInterface
   *   The called request message entity.
   */
  public function setFromUserName($from_user_name);

  /**
   * Gets the ToUserName.
   *
   * @return string
   *   ToUserName of the request message.
   */
  public function getToUserName();

  /**
   * Sets the to_user_name.
   *
   * @param string $to_user_name
   *   The request message to_user_name.
   *
   * @return \Drupal\wechat\WechatRequestMessageInterface
   *   The called request message entity.
   */
  public function setToUserName($to_user_name); 

  /**
   * Gets the CreateTime.
   *
   * @return string
   *   CreateTime of the request message.
   */
  public function getCreateTime();

  /**
   * Sets the create_time.
   *
   * @param string $create_time
   *   The request message create_time.
   *
   * @return \Drupal\wechat\WechatRequestMessageInterface
   *   The called request message entity.
   */
  public function setCreateTime($create_time);    

}
