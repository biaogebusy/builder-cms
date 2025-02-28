<?php

/**
 * @file
 * Contains \Drupal\wechat_menu\Controller\WechatMenuController.
 */

namespace Drupal\wechat_menu\Controller;

use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a manage interface for wechat menu item.
 */
class WechatMenuController {

  /**
   *
   */
  public function wechatMenuManagePage() {
    //ToDo
    $output = "";
	//$output .= '123456';
	/*
	$sql = "select * from wechat_menu_item where parent=:parent ORDER BY weight ASC";
	$result = db_query($sql, array(':parent'=> 0,));
	*/
	
    $query = \Drupal::entityQuery('wechat_menu_item');
    $query->condition('parent', 0);
	$query->range(0, 100);
	$query->sort('weight', 'ASC');

    $wmi_ids = $query->execute();
    $wechat_menu_items = \Drupal::entityManager()
      ->getStorage('wechat_menu_item')
      ->loadMultiple($wmi_ids);	  
	/*
    $wechat_menu_items = \Drupal::entityManager()
      ->getStorage('wechat_menu_item')
      ->loadByProperties(['parent' => 0]);	
	  */
	//TODO,这里将会完善为可以拖动的表单。不过现在先默认
	$output .= '<table>';
	$output .= '<tr><td width="80%">菜单项</td><td>操作</td></tr>';
	foreach($wechat_menu_items as $wmi){
	  $edit_link = Link::fromTextAndUrl('编辑', Url::fromRoute('entity.wechat_menu_item.canonical',['wechat_menu_item' => $wmi->id()])) ;
	  $output .= '<tr class="parent" style="background-color:yellow;"><td width="80%">' . $wmi->title->value.'</td><td>'. $edit_link->toString() .'</td></tr>';
	  $is_parent = $wmi->is_parent->value;
	  if(!empty($is_parent)){
		$sub_query = \Drupal::entityQuery('wechat_menu_item');
		$sub_query->condition('parent', $wmi->id());
		$sub_query->range(0, 100);
		$sub_query->sort('weight', 'ASC');

		$sub_wmi_ids = $sub_query->execute();
		$sub_wechat_menu_items = \Drupal::entityManager()
		  ->getStorage('wechat_menu_item')
		  ->loadMultiple($sub_wmi_ids);	  
        foreach($sub_wechat_menu_items as $sub_wmi){
	      $sub_edit_link = Link::fromTextAndUrl('编辑', Url::fromRoute('entity.wechat_menu_item.canonical',['wechat_menu_item' => $sub_wmi->id()])) ;
	      $output .= '<tr><td width="80%" class="sub-button">' . $sub_wmi->title->value.'</td><td>'. $sub_edit_link->toString() .'</td></tr>';
		}  
	  
	  }
	}
	$output .= '</table>';
	
    $build = [
      '#markup' => $output,
    ];
	$build['#attached']['library'][] = 'wechat_menu/menu_item_collection';
    $build['sync_form'] = \Drupal::formBuilder()->getForm('Drupal\wechat_menu\Form\WechatMenuSyncForm');
    return $build;	
	//\Drupal::logger('wechat')->notice(var_export($request_data, true));

  }

}
