<?php

/**
 * @file
 * Contains \Drupal\wechat\WechatResponseMessageInterface.
 */

namespace Drupal\wechat;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a wechat request message entity.
 */
interface WechatResponseMessageInterface extends ContentEntityInterface {
  /**
   * Gets the wechat response message type.
   *
   * @return string
   *   The wechat response message type.
   */
  public function getMsgType();

  /**
   * Gets the request message Id. 
   *
   * @return integer
   *   Id of the request message.
   */
  public function getRmId();

  /**
   * Sets the request message Id.
   *
   * @param integer $rm_id.
   *   
   * @return \Drupal\wechat\WechatRequestMessageInterface
   *   The called request message entity.
   */
  public function setRmId($rm_id);

  /**
   * Gets the FromUserName.
   *
   * @return string
   *   FromUserName of the response message.
   */
  public function getFromUserName();

  /**
   * Sets the from_user_name.
   *
   * @param string $from_user_name
   *   The response message from_user_name.
   *
   * @return \Drupal\wechat\WechatRequestMessageInterface
   *   The called response message entity.
   */
  public function setFromUserName($from_user_name);

  /**
   * Gets the ToUserName.
   *
   * @return string
   *   ToUserName of the response message.
   */
  public function getToUserName();

  /**
   * Sets the to_user_name.
   *
   * @param string $to_user_name
   *   The response message to_user_name.
   *
   * @return \Drupal\wechat\WechatResponseMessageInterface
   *   The called response message entity.
   */
  public function setToUserName($to_user_name); 

  /**
   * Gets the CreateTime.
   *
   * @return integer
   *   CreateTime of the response message.
   */
  public function getCreateTime();

  /**
   * Sets the create_time.
   *
   * @param integer $create_time
   *   The response message create_time.
   *
   * @return \Drupal\wechat\WechatResponseMessageInterface
   *   The called response message entity.
   */
  public function setCreateTime($create_time); 
  
  /**
   * Gets the func_flag.
   *
   * @return integer
   *   func_flag of the response message.
   */
  public function getFuncFlag();

  /**
   * Sets the create_time.
   *
   * @param integer $func_flag
   *   The response message func_flag.
   *
   * @return \Drupal\wechat\WechatResponseMessageInterface
   *   The called response message entity.
   */
  public function setFuncFlag($func_flag);    

}
