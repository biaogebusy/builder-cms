<?php

/**
 * @file
 * Contains \Drupal\wechat_menu\Entity\WechatMenuItem.
 */

namespace Drupal\wechat_menu\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\wechat_menu\WechatMenuItemInterface;

/**
 * Defines the wechat menu entity class.
 *
 * @ContentEntityType(
 *   id = "wechat_menu_item",
 *   label = @Translation("Wechat menu item"),
 *   bundle_label = @Translation("Wechat menu item"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "form" = {
 *       "add" = "Drupal\wechat_menu\Form\WechatMenuItemForm",
 *       "edit" = "Drupal\wechat_menu\Form\WechatMenuItemForm", 
 *       "delete" = "Drupal\wechat_menu\Form\WechatMenuItemDeleteForm"
 *     }
 *   },
 *   admin_permission = "access administration pages",
 *   base_table = "wechat_menu_item",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title"
 *   },
 *   links = {
 *     "canonical" = "/admin/wechat/config/menumanage/{wechat_menu_item}",
 *     "edit-form" = "/admin/wechat/config/menu/manage/{wechat_menu_item}",
 *     "delete-form" = "/admin/wechat/config/menu/manage/{wechat_menu_item}/delete",
 *   } 
 * )
 *
 */
class WechatMenuItem extends ContentEntityBase implements WechatMenuItemInterface {


  /**
   * {@inheritdoc}
   */
  public function getWeight(){
    return $this->get('weight')->value;  
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight){
    $this->set('weight', $weight);
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
      ->setLabel(t('menu item id'))
      ->setDescription(t('The wechat menu item ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);
    /*
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The wechat request message UUID.'))
      ->setReadOnly(TRUE);
    */
    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('title'))
      ->setDescription(t('The wechat menu item title.'))
      ->setSetting('max_length', 255);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('type'))
      ->setDescription(t('Menu item type.'))
      ->setSetting('max_length', 255);

    $fields['wechat_key'] = BaseFieldDefinition::create('string')
      ->setLabel(t('key'))
      ->setDescription(t('Menu key.'))
      ->setSetting('max_length', 255);

    $fields['url'] = BaseFieldDefinition::create('string')
      ->setLabel(t('URL'))
      ->setDescription(t('The menu open url.'));
	  
    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight'))
      ->setDescription(t('The menu item weight among items at the same depth.'))
      ->setDefaultValue(0);	
	  
    $fields['is_parent'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Is parent'))
      ->setDescription(t('Flag of menu item is parent.'))
      ->setDefaultValue(FALSE);	  
	  
    $fields['parent'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Parent'))
      ->setDescription(t('Parent item.'))
      ->setDefaultValue(0);	

    $fields['data'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Data'))
      ->setDescription(t('A serialized array of extra data.'));

    return $fields;
  }


}
