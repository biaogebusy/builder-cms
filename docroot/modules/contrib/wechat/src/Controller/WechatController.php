<?php

/**
 * @file
 * Contains \Drupal\wechat\Controller\WechatController.
 */

namespace Drupal\wechat\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides a callback for wechat.
 */
class WechatController {

  /**
   *
   */
  public function wechatCallbackPage() {
    $we_obj = _wechat_init_obj();
    $we_obj->valid();
    $type = $we_obj->getRev()->getRevType();
    $request_data = $we_obj->getRevData();
	$request_data = $this->xmlToArray($request_data);
    //watchdog('wechat', '123');
	//\Drupal::logger('wechat')->notice(var_export($request_data, true));
    $request_message = wechat_build_request_message($request_data);
    //watchdog('wechat', '123456'); 
	wechat_build_response_message($request_message);
    //$response_message = wechat_build_response_message($request_message);
    //$response_message->send();  
    /*
     \Drupal::logger('wechat')->notice("wechat callback in");
    $signature = isset($_GET["signature"]) ? $_GET["signature"] : "";
    $timestamp = isset($_GET["timestamp"]) ? $_GET["timestamp"] : "";
    $nonce = isset($_GET["nonce"]) ? $_GET["nonce"] : "";	
    $config = \Drupal::config('wechat.default');
    $token = $config->get('token');
     \Drupal::logger('wechat')->notice(";signature:" . $signature . ";timestamp:" . $timestamp .  ";nonce:" . $nonce . ";token:" . $token);
    $tmpArr = array($token, $timestamp, $nonce);
    sort($tmpArr);
    $tmpStr = implode( $tmpArr );
    $tmpStr = sha1( $tmpStr );
	
    if( $tmpStr == $signature ){
    }else{
      echo 'Invalid input';	
      exit(0);
    }
     \Drupal::logger('wechat')->notice("123456");
	   
    //获取echostr，验证时用
    $echostr = isset($_GET['echostr']) ? $_GET['echostr'] : "";
    if(!empty($echostr)){
      echo $echostr;
      exit();
    }
    //global $HTTP_RAW_POST_DATA; 
    //$post_data  = $HTTP_RAW_POST_DATA; 
    $post_data = file_get_contents('php://input', true);
    if(empty($post_data)) {
	  \Drupal::logger('wechat')->notice("wrong input HTTP_RAW_POST_DATA");
      echo t('wrong input');
      exit();
    } 
     \Drupal::logger('wechat')->notice("123456abc");	
    $xml_obj = simplexml_load_string($post_data, 'SimpleXMLElement', LIBXML_NOCDATA);
    if(empty($xml_obj)) {
      echo t('wrong input');
      exit();  
    }
    $from_user_name = $xml_obj->FromUserName;  
    $to_user_name = $xml_obj->ToUserName;
    $msg_type = $xml_obj->MsgType;
    $return_msg = '';
    if('text' != $msg_type){
      $return_msg = t('Only support text message');
    }
    else{
      $return_msg = $xml_obj->Content;
    }
    //$return_msg = 'Drupal test' . $return_msg;
    //watchdog('wechat', 'ID:' . $xml_obj->MsgId . ' Content' . $xml_obj->Content);
    $return_template = 
    "<xml>
      <ToUserName><![CDATA[%s]]></ToUserName>
      <FromUserName><![CDATA[%s]]></FromUserName>
      <CreateTime>%s</CreateTime>
      <MsgType><![CDATA[text]]></MsgType>
      <Content><![CDATA[%s]]></Content>
      <FuncFlag>0</FuncFlag>
    </xml>";
	\Drupal::logger('wechat')->notice("123456abcefg");
    $result_str = sprintf($return_template, $from_user_name, $to_user_name, time(), $return_msg);
    echo $result_str;
	exit();  
	*/
  }
  
  public function xmlToArray($simpleXmlElement){  
    $simpleXmlElement=(array)$simpleXmlElement;  
    foreach($simpleXmlElement as $k=>$v){  
        if($v instanceof SimpleXMLElement ||is_array($v)){  
            $simpleXmlElement[$k]= $this->xmlToArray($v);  
        }  
    }  
    return $simpleXmlElement;  
  }  

}
