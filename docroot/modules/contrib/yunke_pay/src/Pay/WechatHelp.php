<?php
/**
 * 在微信浏览器中下单支付时，需要用户的openid，本类继续处理用户授权后的下单操作
 *
 * 本类没有使用翻译函数，如有翻译需求的用户请自行添加 示例：$this->t('被翻译的内容')
 *
 * 开发者: yunke
 * Email: phpworld@qq.com
 * Date: 2021/6/13
 */

namespace Drupal\yunke_pay\Pay;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use \Drupal\Core\Routing\TrustedRedirectResponse;
use \Symfony\Component\HttpFoundation\Response;
use \Drupal\Core\Url;

class WechatHelp extends ControllerBase {

  //微信接口配置
  protected $config;

  //日志记录器
  protected $logger;


  public function __construct() {
    $this->config = $this->config('yunke_pay.WeChat');
    $this->logger = $this->getLogger('yunke_pay');
  }


  public function index(Request $request) {
    $code = $request->query->get('code');
    $session = $this->getSession();
    $yunkePay = $session->get('yunke_pay', []);//本模块所有数据均储存在该键下
    if (empty($yunkePay['suspendedOrder'])) {
      return new Response('没有订单需要处理');
    }

    if (empty($code)) { //未授权或系统错误,重新返回到付款页面并给出提示
      $this->messenger()->addWarning('无法获取到你的openid，请点击右上角菜单然后选择在浏览器中打开继续付款');
      $redirectURL = $yunkePay['suspendedOrder']['return_url'];
      return new TrustedRedirectResponse($redirectURL);
    }
    $wechatAPI = \Drupal::service('yunke_pay.pay.wechat');
    $openID = $wechatAPI->getOpenId($code);
    if ($openID == FALSE) {
      $this->messenger()->addWarning('无法获取到你的openid，请点击右上角菜单然后选择在浏览器中打开继续付款');
      $redirectURL = $yunkePay['suspendedOrder']['return_url'];
      return new TrustedRedirectResponse($redirectURL);
    }
    $yunkePay['wechatOpenID'] = $openID;
    $session->set('yunke_pay', $yunkePay); //储存openid避免以后再次获取
    $response = $wechatAPI->order($yunkePay['suspendedOrder']);
    if ($response instanceof Response) {
      return $response;
    }
    $this->messenger()->addWarning($response);
    $redirectURL = $yunkePay['suspendedOrder']['return_url'];
    return new TrustedRedirectResponse($redirectURL);
  }

  /**
   * 得到会话对象 用于保存用户的openid等
   *
   * @return \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected function getSession() {
    $request = \Drupal::request();
    if (!$request->hasSession()) {
      //通常不会执行这里，在系统初期http堆栈阶段，服务http_middleware.session会启动会话
      //但预防其他模块干扰，此处以备会话丢失，确保验证器以此得到会话：$session = $request->getSession();
      $session = \Drupal::service('session');
      $session->start();
      $request->setSession($session);
    }
    return $request->getSession();
  }

  public function qrcodePay($order) {
    $wechatAPI = \Drupal::service('yunke_pay.pay.wechat');
    $storage = $wechatAPI->getStorage();
    $payData = $storage->get($order, []);
    if (!empty($payData['codeURL'])) {
      $codeURL = $payData['codeURL']; //从本地储存器中获取数据，避免重复下单引起错误
      $returnURL = $payData['return_url'];
    }
    else {
      return ['#markup' => '订单不存在或已经超期',];
    }
    //返回二维码
    $response = [
      '#title' => '请用微信扫码付款',
    ];
    $response['qrcode'] = [
      '#type'       => 'yunke_qrcode',
      '#text'       => $codeURL,
      '#foreground' => "#33CC99",
    ];
    $response['returnURL'] = [
      '#type'       => 'link',
      '#title'      => '付款后点此返回',
      '#url'        => Url::fromUri($returnURL),
      '#attributes' => [
        'class' => ['button'],
      ],
    ];
    return $response;
  }

}
