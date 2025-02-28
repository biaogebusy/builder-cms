<?php

/**
 * @file
 * Contains \Drupal\wechat\Entity\WechatRequestMessage.
 */

namespace Drupal\wechat\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\wechat\WechatRequestMessageInterface;

/**
 * Defines the request message entity class.
 *
 * @ContentEntityType(
 *   id = "wechat_request_message",
 *   label = @Translation("Request message"),
 *   bundle_label = @Translation("Request message type"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "list_builder" = "Drupal\wechat\WechatRequestMessageListBuilder",
 *     "view_builder" = "Drupal\wechat\WechatRequestMessageViewBuilder",
 *     "views_data" = "Drupal\wechat\WechatRequestMessageViewsData"
 *   },
 *   admin_permission = "access administration pages",
 *   base_table = "wechat_request_message",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "msg_type",
 *     "label" = "msg_id"
 *   },
 *   bundle_entity_type = "wechat_request_message_type",
 *   field_ui_base_route = "entity.wechat_request_message_type.edit_form",
 * )
 *
 */
class WechatRequestMessage extends ContentEntityBase implements WechatRequestMessageInterface {
  /**
   * {@inheritdoc}
   */
  public function getMsgType() {
    return $this->bundle();
  }

  /**
   * {@inheritdoc}
   */
  public function getMsgId() {
    return $this->get('msg_id')->value;;
  }

  /**
   * {@inheritdoc}
   */
  public function setMsgId($msg_id){
    $this->set('msg_id', $msg_id);
    return $this;  
  }

  /**
   * {@inheritdoc}
   */
  public function getFromUserName(){
    return $this->get('from_user_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setFromUserName($from_user_name){
    $this->set('from_user_name', $from_user_name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getToUserName(){
    return $this->get('to_user_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setToUserName($to_user_name){
    $this->set('to_user_name', $to_user_name);
    return $this;  
  }

  /**
   * {@inheritdoc}
   */
  public function getCreateTime(){
    return $this->get('create_time')->value;  
  }

  /**
   * {@inheritdoc}
   */
  public function setCreateTime($create_time){
    $this->set('create_time', $create_time);
    return $this;  
  }
  
  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Request message ID'))
      ->setDescription(t('The wechat request message ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);
    /*
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The wechat request message UUID.'))
      ->setReadOnly(TRUE);
    */
    $fields['msg_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('msg_id'))
      ->setDescription(t('The msg_id.'))
      ->setSetting('max_length', 255);

    $fields['to_user_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('To user name'))
      ->setDescription(t('The to user name.'))
      ->setSetting('max_length', 255);

    $fields['from_user_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('From user name'))
      ->setDescription(t('The from user name.'))
      ->setSetting('max_length', 255);

    $fields['create_time'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Create time'))
      ->setDescription(t('The time that the request message was created.'));
	  
    $fields['msg_type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Msg type'))
      ->setDescription(t('The msg type.'))
      ->setSetting('target_type', 'wechat_request_message_type');

    $fields['data'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Data'))
      ->setDescription(t('A serialized array of extra data.'));

    return $fields;
  }


}
