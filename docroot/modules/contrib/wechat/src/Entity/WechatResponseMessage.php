<?php

/**
 * @file
 * Contains \Drupal\wechat\Entity\WechatResponseMessage.
 */

namespace Drupal\wechat\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\wechat\WechatResponseMessageInterface;

/**
 * Defines the response message entity class.
 *
 * @ContentEntityType(
 *   id = "wechat_response_message",
 *   label = @Translation("Response message"),
 *   bundle_label = @Translation("Response message type"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "list_builder" = "Drupal\wechat\WechatResponseMessageListBuilder",
 *     "view_builder" = "Drupal\wechat\WechatResponseMessageViewBuilder",
 *     "views_data" = "Drupal\wechat\WechatResponseMessageViewsData",
 *     "form" = {
 *       "default" = "Drupal\wechat\Form\WechatResponseMessageForm",
 *       "add" = "Drupal\wechat\Form\WechatResponseMessageForm",
 *       "edit" = "Drupal\wechat\Form\WechatResponseMessageForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "access administration pages",
 *   base_table = "wechat_response_message",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "msg_type",
 *     "label" = "rm_id"
 *   },
 *   links = {
 *     "canonical" = "/response_message/{wechat_response_message}",
 *     "add-page" = "/response_message/add",
 *     "add-form" = "/response_message/add/{wechat_response_message_type}",
 *     "edit-form" = "/response_message/{wechat_response_message}/edit",
 *     "delete-form" = "/response_message/{wechat_response_message}/delete",
 *     "collection" = "/admin/wechat/messages/response"
 *   },
 *   bundle_entity_type = "wechat_response_message_type",
 *   field_ui_base_route = "entity.wechat_response_message_type.edit_form",
 * )
 *
 */
class WechatResponseMessage extends ContentEntityBase implements WechatResponseMessageInterface {
  /**
   * {@inheritdoc}
   */
  public function getMsgType() {
    return $this->bundle();
  }

  /**
   * {@inheritdoc}
   */
  public function getRmId() {
    return $this->get('rm_id')->value;;
  }

  /**
   * {@inheritdoc}
   */
  public function setRmId($rm_id){
    $this->set('rm_id', $rm_id);
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
  public function getFuncFlag() {
    return $this->get('func_flag')->value;;
  }

  /**
   * {@inheritdoc}
   */
  public function setFuncFlag($func_flag){
    $this->set('func_flag', $func_flag);
    return $this;  
  } 
  
  /**
   * {@inheritdoc}
   */
  public function send(){
    $result_str = wechat_response_message_obj_to_xml($this);
    echo $result_str;
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
      ->setLabel(t('Response message ID'))
      ->setDescription(t('The wechat response message ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);
    /*
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The wechat request message UUID.'))
      ->setReadOnly(TRUE);
    */
    $fields['rm_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('rm_id'))
      ->setDescription(t('The request message id.'))
      ->setSetting('unsigned', TRUE);

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
      ->setDescription(t('The time that the response message was created.'));
	  
    $fields['msg_type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Msg type'))
      ->setDescription(t('The msg type.'))
      ->setSetting('target_type', 'wechat_response_message_type');

    $fields['func_flag'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Func flag'))
      ->setDescription(t('Function flag of wechat response message.'))
      ->setDefaultValue(0);

    return $fields;
  }

  public function sendCustomMessage() {
    $array_data = $this->convertToArray();
  
    $we_obj = wechat_init_obj_with_access_token();
    $result = $we_obj->sendCustomMessage($array_data);
 
    if (!empty($result)) {
      //Todo: Error.
      return $result;
    }
    else{
      return FALSE;
    }
  }
  public function convertToArray() {
    $request_time = time();
    $to_user_name = $this->to_user_name->value;
    $result_array = array();
    $result_array["touser"] = $to_user_name;
    $type = $this->getMsgType();
    if ($type == 'text') {
      $result_array["msgtype"] = "text";
      $content = $this->field_content->value;
      $result_array["text"] = array(
        "content" => $content
      );
    }
    elseif ($type == 'image') {
      $result_array["msgtype"] = "image";
      //$media_id = $message_wrapper->wechat_media_id->value();
	  //$image_uri = $this->field_image->uri;
	   $image = $this->field_image->entity;
	   $image_uri = $image->getFileUri();
	 // \Drupal::logger('wechat')->notice(var_export($uri, true));
	   $id = $image->id();
	 // \Drupal::logger('wechat')->notice(var_export($id, true));	  
      if (!empty($image_uri)) {
        $media_id = $this->uploadMedia($image_uri, 'image');
		$result_array["image"] = array(
		  "media_id" => $media_id
		);		
      }

    }

    return $result_array;
  }  

  function uploadMedia($file_uri, $type) {
    $media_id = NULL;
    $uri = $file_uri;
	//\Drupal::logger('wechat')->notice("file_uri:" .$file_uri);
    $realpath = \Drupal::service('file_system')->realpath($uri);
    $filename = '@' . $realpath;
	//\Drupal::logger('wechat')->notice("filename:" .$filename);
    //print debug($filename);
    $we_obj = wechat_init_obj_with_access_token();
    $data = array(
      'media' => $filename,
    );
    $result  = $we_obj->uploadMedia($data, $type);
    //print debug($result);
	//\Drupal::logger('wechat')->notice(var_export($result, true));
    if (!empty($result['media_id']))	{
      $media_id = $result['media_id'];
    }
    return $media_id;
  }
  
}
