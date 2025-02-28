<?php

/**
 * @file
 * Contains \Drupal\wechat\Entity\WechatUser.
 */

namespace Drupal\wechat\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\wechat\WechatUserInterface;

/**
 * Defines the request message entity class.
 *
 * @ContentEntityType(
 *   id = "wechat_user",
 *   label = @Translation("Wechat user"),
 *   bundle_label = @Translation("Wechat user"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "list_builder" = "Drupal\wechat\WechatUserListBuilder",
 *     "view_builder" = "Drupal\wechat\WechatUserViewBuilder",
 *     "views_data" = "Drupal\wechat\WechatUserViewsData",
 *   },
 *   admin_permission = "access administration pages",
 *   base_table = "wechat_user",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "openid"
 *   },
 *   field_ui_base_route = "entity.wechat_user.collection",
 * )
 *
 */
class WechatUser extends ContentEntityBase implements WechatUserInterface {

  /**
   * {@inheritdoc}
   */
  public function getOpenid() {
    return $this->get('openid')->value;;
  }

  /**
   * {@inheritdoc}
   */
  public function setOpenid($openid){
    $this->set('openid', $openid);
    return $this;  
  }
  
  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Wechat User ID'))
      ->setDescription(t('The wechat user integer ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);
    /*
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The wechat request message UUID.'))
      ->setReadOnly(TRUE);
    */
    $fields['openid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('openid'))
      ->setDescription(t('The openid of wechat user.'))
      ->setSetting('max_length', 255);

    $fields['nickname'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Nick name'))
      ->setDescription(t('The nick name of wechat user.'))
      ->setSetting('max_length', 255);

    $fields['province'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Province'))
      ->setDescription(t('The province of wechat user.'))
      ->setSetting('max_length', 255);
	  
    $fields['city'] = BaseFieldDefinition::create('string')
      ->setLabel(t('City'))
      ->setDescription(t('The city of wechat user.'))
      ->setSetting('max_length', 255);	

    $fields['country'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Country'))
      ->setDescription(t('The country of wechat user.'))
      ->setSetting('max_length', 255);
	  
    $fields['headimgurl'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Head img url'))
      ->setDescription(t('The headimgurl of wechat user.'))
      ->setSetting('max_length', 255);	  

    $fields['subscribe_time'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Subscribe time'))
      ->setDescription(t('The subscribe time of wechat user.'));
	  
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Drupal user ID'))
      ->setDescription(t('The drupal user ID of wechat user.'))
      ->setSetting('target_type', 'user');
	  
    $fields['sex'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Sex'))
      ->setDescription(t('The sex of wechat user.'));

    $fields['subscribe'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('subscribe'))
      ->setDescription(t('The subscribe status of wechat user.'));
	  
    $fields['remark'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Remark'))
      ->setDescription(t('The remark of wechat user.'))
      ->setSetting('max_length', 255);
	  
    $fields['groupid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('groupid'))
      ->setDescription(t('The groupid of wechat user.'))
      ->setSetting('max_length', 255);
	  
    $fields['language'] = BaseFieldDefinition::create('string')
      ->setLabel(t('language'))
      ->setDescription(t('The language of wechat user.'))
      ->setSetting('max_length', 255);	  
  
    return $fields;
  }


}
