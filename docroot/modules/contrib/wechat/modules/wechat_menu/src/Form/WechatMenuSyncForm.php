<?php

/**
 * @file
 * Contains \Drupal\wechat_menu\Form\WechatMenuSyncForm.
 */

namespace Drupal\wechat_menu\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\search\SearchPageRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds the wechat menu sync form.
 */
class WechatMenuSyncForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wechat_menu_sync_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('同步到微信'),
      '#name' => '',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This form submits to the search page, so processing happens there.
    $menu_arr = $this->getWechatMenuArr();
    if (empty($menu_arr['button'])) {
      drupal_set_message(t('Empty WeChat Menu'), 'error');
    }
	
    else {
      $we_obj = _wechat_init_obj();
      if ($we_obj->createMenu($menu_arr)) {
        drupal_set_message(t('Update menu success.'));
      }
      else {
        drupal_set_message($we_obj->errMsg . '-' . $we_obj->errCode, 'error');
      }
    }
	
    return;	
  }

  /**
   * get wechat menu array
   */
  public function getWechatMenuArr() {
  
    $query = \Drupal::entityQuery('wechat_menu_item');
    $query->condition('parent', 0);
	$query->range(0, 100);
	$query->sort('weight', 'ASC');

    $wmi_ids = $query->execute();
    $wechat_menu_items = \Drupal::entityManager()
      ->getStorage('wechat_menu_item')
      ->loadMultiple($wmi_ids);

    foreach ($wechat_menu_items as $wechat_menu_item) {
      $element = array();
      $element['name'] = $wechat_menu_item->title->value;
      $element['sub_button'] = array();
	  $is_parent = $wechat_menu_item->is_parent->value;
	  if(!empty($is_parent)){
		$sub_query = \Drupal::entityQuery('wechat_menu_item');
		$sub_query->condition('parent', $wechat_menu_item->id());
		$sub_query->range(0, 5);
		$sub_query->sort('weight', 'ASC');

		$sub_wmi_ids = $sub_query->execute();
		$sub_wechat_menu_items = \Drupal::entityManager()
		  ->getStorage('wechat_menu_item')
		  ->loadMultiple($sub_wmi_ids);	  
        foreach($sub_wechat_menu_items as $sub_wmi){
		  $element['sub_button'][] = $this->convertButtomArray($sub_wmi);
		}

	  }
      if (empty($element['sub_button'])) {
        unset($element['sub_button']);
        $element = $this->convertButtomArray($wechat_menu_item);
      }
      $menu_arr['button'][] = $element;
    }
	
    return $menu_arr;
  }

  /**
   * Drupal menu to wechat menu
   */
  function convertButtomArray($wechat_menu_item) {
    $subelement = array();
    $subelement['name'] =  $wechat_menu_item->title->value;
	$type =  $wechat_menu_item->type->value;
    if ($type == 'view') {
      $subelement['type'] = 'view';
      $subelement['url'] = $wechat_menu_item->url->value;	
    }
    else {
      $subelement['type'] = $type;
      $subelement['key'] = $wechat_menu_item->wechat_key->value;
    }
    return $subelement;
  }
  
}
