<?php

namespace Drupal\wechat_menu\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to add/update wechat menu item.
 */
class WechatMenuItemForm extends ContentEntityForm {

  /**
   * The wechat menu item.
   *
   * @var \Drupal\wechat_menu\WechatMenuItemInterface
   */
  protected $entity;


  /**
   * Constructs a WechatMenuItemForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager) {
    parent::__construct($entity_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['title'] = array(
      '#type' => 'textfield',
      '#title' => t('名称'),
      '#description' => t('菜单名称'),
      '#default_value' => $this->entity->title->value,

    );
    $form['is_parent'] = array(
      '#type' => 'checkbox',
      '#title' => t('父按钮'),
	  '#default_value' => $this->entity->is_parent->value,
    );
    $form['weight'] = array(
      '#type' => 'weight',
      '#title' => t('Weight'),
      '#default_value' => $this->entity->weight->value,
      '#delta' => 10,
    );
	 $form['button'] = array(
	  '#type' => 'fieldset',
		//'#title' => t('Content types'),
		//'#collapsible' => TRUE,
	  '#collapsed' => FALSE,
	  '#group' => 'visibility',
		//'#weight' => 5,
      '#states' => array(
        'visible' => array(
            ':input[name="is_parent"]' => array('checked' => false),
        ),
      ),
	);

    $parent_options = array(
	  '0' => t('请选择一个父项'),
    );
    $query = \Drupal::entityQuery('wechat_menu_item');
    $query->condition('parent', 0);
	$query->condition('is_parent', 1);
	$query->range(0, 100);
	$query->sort('weight', 'ASC');

    $wmi_ids = $query->execute();
    $wechat_menu_items = \Drupal::entityManager()
      ->getStorage('wechat_menu_item')
      ->loadMultiple($wmi_ids);
	foreach($wechat_menu_items as $wmi){
	  $parent_options[$wmi->id()] = $wmi->title->value;
	}
    $form['button']['parent'] = array(
      '#type' => 'select',
      '#title' => t('Parent'),
      '#default_value' => $this->entity->parent->value,
      '#options' => $parent_options,

    );

    $menu_type_options = array(
	  '' => t('请选择一个菜单类型'),
      'click' => t('点击推事件'),
      'view' => t('跳转URL'),
      'scancode_push' => t('扫码推事件'),
      'scancode_waitmsg' => t('扫码推事件且弹出“消息接收中”提示框'),
      'pic_sysphoto' => t('弹出系统拍照发图'),
      'pic_photo_or_album' => t('弹出拍照或者相册发图'),
      'pic_weixin' => t('弹出微信相册发图器'),
      'location_select' => t('弹出地理位置选择器'),
    );
    $form['button']['type'] = array(
      '#type' => 'select',
      '#title' => t('Menu Type'),
      '#default_value' => $this->entity->type->value,
      //'#options' => array('click' => t('Click'), 'view' => t('View')),
      '#options' => $menu_type_options,
      //'#description' => t('Click: 点击推事件, View: 跳转URL, .'),
      //'#required' => TRUE,
    );
    //Todo wechat_key
    $form['button']['wechat_key'] = array(
      '#type' => 'textfield',
      '#title' => t('Menu KEY'),
      '#description' => t('Key for the menu clicked event'),
      '#default_value' => $this->entity->wechat_key->value,
      '#states' => array(
        'visible' => array(
          ':input[name="type"]' => array(
            array('value' => 'click'),
            array('value' => 'scancode_push'),
            array('value' => 'scancode_waitmsg'),
            array('value' => 'pic_sysphoto'),
            array('value' => 'pic_photo_or_album'),
            array('value' => 'pic_weixin'),
            array('value' => 'location_select'),
		  ),
        ),
      ),
    );

    $form['button']['url'] = array(
      '#type' => 'textfield',
      '#title' => t('Menu Open URL'),
	  '#maxlength' => 512,
      '#description' => t('Open this URL if menu type is view.'),
      '#default_value' => $this->entity->url->value,
      '#states' => array(
        'visible' => array(
            ':input[name="type"]' => array('value' => 'view'),
        ),
      ),
    );
	$settings = $this->entity->data->value;
	//drupal_set_message(var_export($settings, true));
	$form['button']['views'] = array(
	  '#type' => 'fieldset',
	  '#title' => t('使用Drupal的视图响应事件'),
	  //'#collapsible' => TRUE,
	  //'#collapsed' => FALSE,
	  '#group' => 'visibility',
      '#states' => array(
        'visible' => array(
            ':input[name="type"]' => array('value' => 'click'),
        ),
      ),
	);
    $form['button']['views']['view_name'] = array(
      '#type' => 'textfield',
      '#title' => t('视图机读名字'),
      '#default_value' => isset($settings['view_name']) ? $settings['view_name'] : '',
	);
    $form['button']['views']['view_display'] = array(
      '#type' => 'textfield',
      '#title' => t('视图显示名字'),
      '#default_value' => isset($settings['view_display']) ? $settings['view_display'] : '',
	);
    $form['button']['views']['view_arg'] = array(
      '#type' => 'textfield',
      '#title' => t('视图参数'),
	  '#description' => t('视图参数，多个参数使用/分隔'),
      '#default_value' => isset($settings['view_arg']) ? $settings['view_arg'] : '',
	);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $element = parent::actions($form, $form_state);
    $element['submit']['#button_type'] = 'primary';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $id = $this->entity->id();
	//drupal_set_message('id:' . $id);
	$is_parent =  $form_state->getValue('is_parent');
	//drupal_set_message('is_parent:' . $is_parent);
	if(!empty($id)){
	  return;
	}
	//drupal_set_message('id123:' . $id);
	//一级菜单按钮最多不能超过3个
	//$is_parent = $this->entity->is_parent->value;


	if(!empty($is_parent)){
	  $query = \Drupal::entityQuery('wechat_menu_item');
      $query->condition('parent', 0);
	  $query->range(0, 100);
	  $query->sort('weight', 'ASC');

      $wmi_ids = $query->execute();
	  //drupal_set_message('count:' . count($wmi_ids));
	  if(count($wmi_ids) >= 3){
	    $form_state->setErrorByName('title', $this->t('一级菜单按钮最多不能超过3个.'));
	  }
	}

	//一级菜单按钮最多不能超过3个
	//$is_parent = $this->entity->is_parent->value;
	if(empty($is_parent)){
	  $query = \Drupal::entityQuery('wechat_menu_item');
      $query->condition('is_parent', 0);
	  $query->range(0, 100);
	  $query->sort('weight', 'ASC');

      $wmi_ids = $query->execute();
	  if(count($wmi_ids) >= 15){
	    $form_state->setErrorByName('title', $this->t('二级菜单按钮最多不能超过15个.'));
	  }
	}
	//drupal_set_message('id:' . $id);
	//drupal_set_message('weight:' . $this->entity->weight->value);

    //parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $entity */
    $entity = parent::buildEntity($form, $form_state);
	// Todo
/*
    list($menu_name, $parent) = explode(':', $form_state->getValue('menu_parent'), 2);

    $entity->parent->value = $parent;
    $entity->menu_name->value = $menu_name;
    $entity->enabled->value = (!$form_state->isValueEmpty(array('enabled', 'value')));
    $entity->expanded->value = (!$form_state->isValueEmpty(array('expanded', 'value')));
	*/
	$view_name = $form_state->getValue('view_name');
	$view_display = $form_state->getValue('view_display');
	$view_arg = $form_state->getValue('view_arg');
	if(!empty($view_name)){
	  $entity->data->value = array(
	    'view_name' => $view_name,
		'view_display' => $view_display,
		'view_arg' => $view_arg,
	  );
	}

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // The entity is rebuilt in parent::submit().
    $wechat_menu_item = $this->entity;
    $saved = $wechat_menu_item->save();

    if ($saved) {
      drupal_set_message($this->t('The menu item has been saved.'));
      $form_state->setRedirect(
        'entity.wechat_menu_item.collection',
        array('wechat_menu_item' => $wechat_menu_item->id())
      );
    }
    else {
      drupal_set_message($this->t('There was an error saving the item.'), 'error');
      $form_state->setRebuild();
    }
  }

}
