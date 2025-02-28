<?php
/**
 * 本类封装了微信支付接口相关操作，开发前需要进行正确的接口配置，上层订单系统直接调用本类即可
 * 虽然本类已经封装了全部操作  但开发者仍然需要理解微信支付返回数据的含义才能进行订单系统的开发
 * 因此本类及其方法均列出了参考文档以供快速查阅
 *
 * 微信支付文档： https://pay.weixin.qq.com/wiki/doc/apiv3/index.shtml
 * 微信支付首页： https://pay.weixin.qq.com
 *
 * User: yunke
 * Email: phpworld@qq.com
 * Date: 2021/5/18
 *
 * 为了保证安全 本类没有采用任何第三方封装件 仅使用腾讯公司官方提供的SDK
 */

namespace Drupal\yunke_pay\Pay;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Url;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Routing\TrustedRedirectResponse;
use WechatPay\GuzzleMiddleware\Util\AesUtil;
use WechatPay\GuzzleMiddleware\WechatPayMiddleware;
use WechatPay\GuzzleMiddleware\Util\PemUtil;
use Drupal\yunke_pay\Certificate\RefreshWechatCertificate;
use Symfony\Component\HttpFoundation\Request;
use WechatPay\GuzzleMiddleware\Auth\CertificateVerifier;
use WechatPay\GuzzleMiddleware\Auth\PrivateKeySigner;
use Symfony\Component\HttpFoundation\Response;

class Wechat {

  const SCENE_LIMIT = '订单号重复，可能是因为微信支付场景限制，请尝试在首次支付时的场景下付款（场景有：PC端浏览器、移动浏览器、微信内），或更换其他支付方式';

  //微信接口配置
  protected $config;

  //日志记录器
  protected $logger;

  //HTTP请求客户端
  protected $client;

  public function __construct(ConfigFactoryInterface $configFactory, LoggerChannelFactoryInterface $channelFactory) {
    $this->config = $configFactory->get('yunke_pay.WeChat');
    $this->logger = $channelFactory->get('yunke_pay');
    $this->client = $this->getHTTPClient();
  }

  /**
   * 下单付款接口
   * 支付正常时返回响应对象  异常时返回字符串表示的原因
   *
   * @param $order array 订单数组 详见方法内解释
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse|string|\Symfony\Component\HttpFoundation\Response
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_3_1.shtml  H5下单
   * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_1.shtml  JSAPI下单
   * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_4_1.shtml  Native下单
   */
  public function order($order) {
    /**
     * 订单数组如下：
     * $order['order_number']
     * 必选 商户订单号 长度string[6,32] 商户系统内部订单号，只能是数字、大小写字母_-*且在同一个商户号下唯一
     * $order['description']
     * 必选 商品描述 长度string[1,127]
     * $order['total']
     * 必选 总金额 int 订单总金额，单位为分。
     * $order['notify_url']
     * 必选 通知地址 string[1,256] 通知URL必须为直接可访问的URL（不能重定向），不允许携带查询串。
     * $order['return_url']
     * 必选 返回链接 付款完成后重定向的链接
     * $order['timeout_express']
     * 必选 超时时间戳 超过该时间交易将无法下单
     */

    $type = $this->getUserAgentType();
    /**
     * 默认支付场景有：PC（电脑端网页）、WeChat（微信客户端）、Mobile（移动端网页）
     * 微信支付在一种支付场景下如发生下单未付款，则该单后续付款须在原场景下进行，其他场景下会提示201重复下单错误
     * 针对此种情况，首次下单时可在此处储存该单的场景值到有期限控制的键值储存器中，便于后续进行场景判断并给予提示，
     * 对微信的这一缺陷，彻底解决办法是在不同场景下生成不同单号，比如追加场景值前缀，但这又关系到退款逻辑等
     * 鉴于订单号管理不应属于支付模块的责任，且该情况发生几率较小，因此这里支付模块不做如此复杂的处理，
     * 如有需求请在订单系统中处理，调用getUserAgentType()预先生成不同单号
     */
    if ($type == 'Mobile') {
      return $this->orderH5($order);
    }
    elseif ($type == 'WeChat') {
      return $this->orderJSAPI($order);
    }
    elseif ($type == 'PC') {
      return $this->orderNative($order);
    }
    else {
      return $this->orderH5($order); //移动优先
    }

  }

  /**
   * PC端网页支付场景，生成二维码后用微信扫码支付
   *
   * @param $order
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse|string
   * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_4_1.shtml  Native下单
   */
  protected function orderNative($order) {
    $codeURL = NULL;
    $config = $this->config;
    $body = [
      "appid"        => $config->get('appId'),
      //必选 应用ID
      "mchid"        => $config->get('merchantId'),
      //必选 商户号 直连商户的商户号，由微信支付生成并下发
      "description"  => $order['description'] ?: 'None',
      //必选 商品描述 长度string[1,127]
      "out_trade_no" => $order['order_number'],
      //必选 商户订单号 长度string[6,32] 商户系统内部订单号，只能是数字、大小写字母_-*且在同一个商户号下唯一
      "notify_url"   => $order['notify_url'],
      //必选 通知地址 string[1,256] 通知URL必须为直接可访问的URL，不允许携带查询串。如"https://www.weixin.qq.com/wxpay/pay.php"
      "amount"       => [ //必选 订单金额信息
        "total"    => $order['total'], //必选 总金额 int 订单总金额，单位为分。
        "currency" => "CNY", //可选 货币类型标识 string[1,16] CNY：人民币，境内商户号仅支持人民币。
      ],

      "time_expire" => date('Y-m-d\TH:i:sP', (int) $order['timeout_express']),
      //可选 交易结束时间 string[1,64] 订单失效时间，遵循rfc3339标准格式 示例值：2018-06-08T10:34:56+08:00
      //见date('Y-m-d\TH:i:sP', time());
      //"attach"      => 'yunke',//可选 附加数据 string[1,128] 在查询API和支付通知中原样返回，可作为自定义参数使用
      //这里我们不要传递附加数据 这会导致重复下单错误问题 当用户之前没有支付成功，再次拉起支付时，
      //只有描述、金额、附加数据完全一致，相同订单号才能发起重新支付，否则提示订单重复，因此需要保证每次附加数据相同
    ];

    try {
      $resp = $this->client->request(
        'POST',
        $config->get('api.native.orderURL') ?: 'https://api.mch.weixin.qq.com/v3/pay/transactions/native', //请求URL
        [
          'json'    => $body,
          'headers' => ['Accept' => 'application/json'],
        ]
      );
      $statusCode = $resp->getStatusCode();
      if ($statusCode == 200) { //处理成功
        $json = json_decode($resp->getBody()->getContents());
        if (!empty($json->code_url)) {
          $codeURL = $json->code_url;
        }
      }
    } catch (RequestException $e) {
      // 进行错误处理
      $msg = "微信PC支付换取二维码链接失败:" . $e->getMessage() . "\n";
      if ($e->hasResponse()) {
        $msg .= " Status Code:" . $e->getResponse()
            ->getStatusCode() . "; Body:" . $e->getResponse()
            ->getBody() . "\n";
      }
      $this->logger->error($msg);
      if (strpos($msg, '订单号重复') !== FALSE) { //201 商户订单号重复
        $msg = static::SCENE_LIMIT;
      }
      return $msg;
    } catch (\Exception $e) {
      $msg = $e->getMessage();
      $this->logger->error($msg);
      return $msg;
    }
    if (empty($codeURL)) {
      $msg = '微信支付获取code_url失败，无法完成支付';
      return $msg;
    }

    //现在跳转到二维码付款页面 储存付款信息
    $payData = [];
    $payData['codeURL'] = $codeURL;
    $payData['return_url'] = $order['return_url'];
    $expire = (int) $order['timeout_express'] - time();
    $storage = $this->getStorage();
    $storage->setWithExpire($order['order_number'], $payData, $expire);

    $route_parameters = [
      'order' => $order['order_number'],
    ];
    $options = [
      'absolute' => TRUE,
    ];
    $notify_url = new Url('yunke_pay.wechat.native_order.qrcode', $route_parameters, $options);
    return new TrustedRedirectResponse($notify_url->toString(FALSE));
  }

  /**
   * 移动浏览器中的支付，即H5支付
   *
   * @param $order
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse|string 成功时返回跳转响应，失败时返回原因字符串
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_3_1.shtml  H5下单
   */
  protected function orderH5($order) {
    $h5URL = NULL; //H5支付链接
    $config = $this->config;
    // 统一下单JSON请求体 参数见：https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_3_1.shtml
    $body = [
      "appid"        => $config->get('appId'),
      //必选 应用ID
      "mchid"        => $config->get('merchantId'),
      //必选 商户号 直连商户的商户号，由微信支付生成并下发
      "description"  => $order['description'] ?: 'None',
      //必选 商品描述 长度string[1,127]
      "out_trade_no" => $order['order_number'],
      //必选 商户订单号 长度string[6,32] 商户系统内部订单号，只能是数字、大小写字母_-*且在同一个商户号下唯一
      "notify_url"   => $order['notify_url'],
      //必选 通知地址 string[1,256] 通知URL必须为直接可访问的URL，不允许携带查询串。如"https://www.weixin.qq.com/wxpay/pay.php"
      "amount"       => [ //必选 订单金额信息
        "total"    => $order['total'], //必选 总金额 int 订单总金额，单位为分。
        "currency" => "CNY", //可选 货币类型标识 string[1,16] CNY：人民币，境内商户号仅支持人民币。
      ],
      "scene_info"   => [ //必选 支付场景描述
        "payer_client_ip" => \Drupal::request()->getClientIp(),
        //必选 用户终端IP string[1,45] 用户的客户端IP，支持IPv4和IPv6两种格式的IP地址。
        "h5_info"         => [// 必选 H5场景信息
          "type" => "Wap",
          //必选 场景类型 string[1,32] 场景类型 示例值：iOS, Android, Wap
        ],
      ],

      "time_expire" => date('Y-m-d\TH:i:sP', (int) $order['timeout_express']),
      //可选 交易结束时间 string[1,64] 订单失效时间，遵循rfc3339标准格式 示例值：2018-06-08T10:34:56+08:00
      //见date('Y-m-d\TH:i:sP', time());
    ];
    $msg = NULL; //错误消息
    try {
      $resp = $this->client->request(
        'POST',
        $config->get('api.h5.orderURL') ?: 'https://api.mch.weixin.qq.com/v3/pay/transactions/h5', //请求URL
        [
          'json'    => $body,
          'headers' => ['Accept' => 'application/json'],
        ]
      );
      $statusCode = $resp->getStatusCode();
      if ($statusCode == 200) { //处理成功
        $json = json_decode($resp->getBody()->getContents());
        if (!empty($json->h5_url)) {
          $h5URL = $json->h5_url . '&redirect_url=' . urlencode($order['return_url']);
        }
        else {
          $msg = 'H5 跳转链接不存在';
        }
      }
      else {
        $msg = '错误，响应状态码：' . $statusCode;
      }
    } catch (RequestException $e) {
      // 进行错误处理
      $msg = '微信支付换取H5支付链接失败, ' . $e->getMessage() . "\n";
      if ($e->hasResponse()) {
        $msg .= "Status Code:" . $e->getResponse()
            ->getStatusCode() . "; Body:" . $e->getResponse()
            ->getBody() . "\n";
      }
      $this->logger->error($msg);
      if (strpos($msg, '订单号重复') !== FALSE) { //201 商户订单号重复
        $msg = static::SCENE_LIMIT;
      }
    } catch (\Exception $e) {
      $msg = $e->getMessage();
      $this->logger->error($msg);
    }
    if (!empty($h5URL)) {
      return new TrustedRedirectResponse($h5URL);
    }
    else {
      return $msg;
    }
  }


  /**
   * 在微信浏览器内部支付 通过JS调用发起付款
   *
   * @param $order
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse|\Symfony\Component\HttpFoundation\Response
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_1.shtml  JSAPI下单
   */
  protected function orderJSAPI($order) {
    $prepayID = NULL; //预支付id
    //由于微信内部浏览器发起的支付必须要用户的OpenID ,因此先查询OpenID是否存在
    //如果没有则来一次跳转进行获取，在获取到后再进行支付
    $session = $this->getSession();
    $yunkePay = $session->get('yunke_pay', []);//本模块所有数据均储存在该键下
    if (empty($yunkePay['wechatOpenID'])) {//现在需要进行用户授权操作以取得用户OpenID
      $yunkePay['suspendedOrder'] = $order;//将订单数据挂起，以便用户授权后继续操作
      $session->set('yunke_pay', $yunkePay);
      $redirectURL = new Url('yunke_pay.wechat.jsapi_order', [], ['absolute' => TRUE,]);
      $redirectURL = $redirectURL->toString(FALSE);//授权后返回系统的地址
      $oauth2URL = $this->getOauth2URL($redirectURL); //得到获取openid的授权链接
      return new TrustedRedirectResponse($oauth2URL);
    }
    else {
      $openID = $yunkePay['wechatOpenID'];
      unset($yunkePay['suspendedOrder']);
      $session->set('yunke_pay', $yunkePay);
    }
    //已经获取到openid 继续处理订单
    $config = $this->config;
    $body = [
      "appid"        => $config->get('appId'),
      //必选 应用ID
      "mchid"        => $config->get('merchantId'),
      //必选 商户号 直连商户的商户号，由微信支付生成并下发
      "description"  => $order['description'] ?: 'None',
      //必选 商品描述 长度string[1,127]
      "out_trade_no" => $order['order_number'],
      //必选 商户订单号 长度string[6,32] 商户系统内部订单号，只能是数字、大小写字母_-*且在同一个商户号下唯一
      "notify_url"   => $order['notify_url'],
      //必选 通知地址 string[1,256] 通知URL必须为直接可访问的URL，不允许携带查询串。如"https://www.weixin.qq.com/wxpay/pay.php"
      "amount"       => [ //必选 订单金额信息
        "total"    => $order['total'], //必选 总金额 int 订单总金额，单位为分。
        "currency" => "CNY", //可选 货币类型标识 string[1,16] CNY：人民币，境内商户号仅支持人民币。
      ],
      "payer"        => [
        "openid" => $openID,
        //微信浏览器内下单必须要用户openId 类似："oUpF8uMuAJO_M2pxb1Q9zNjWeS6o"，
        //云客个人觉得完全没必要，为系统设计带来不优雅，腾讯的这种做法难以接受
      ],

      "time_expire" => date('Y-m-d\TH:i:sP', (int) $order['timeout_express']),
      //可选 交易结束时间 string[1,64] 订单失效时间，遵循rfc3339标准格式 示例值：2018-06-08T10:34:56+08:00
      //见date('Y-m-d\TH:i:sP', time());
    ];

    try {
      $resp = $this->client->request(
        'POST',
        $config->get('api.js.orderURL') ?: 'https://api.mch.weixin.qq.com/v3/pay/transactions/jsapi', //请求URL
        [
          'json'    => $body,
          'headers' => ['Accept' => 'application/json'],
        ]
      );
      $statusCode = $resp->getStatusCode();
      if ($statusCode == 200) { //处理成功
        $json = json_decode($resp->getBody()->getContents());
        if (!empty($json->prepay_id)) {
          $prepayID = $json->prepay_id;
        }
      }
    } catch (RequestException $e) {
      // 进行错误处理
      $msg = $e->getMessage() . "\n";
      if ($e->hasResponse()) {
        $msg .= "微信支付预交易单创建失败, Status Code:" . $e->getResponse()
            ->getStatusCode() . "; Body:" . $e->getResponse()
            ->getBody() . "\n";
      }
      $this->logger->error($msg);
      if (strpos($msg, '订单号重复') !== FALSE) { //201 商户订单号重复
        $msg = static::SCENE_LIMIT;
      }
      return $msg;
    } catch (\Exception $e) {
      $msg = $e->getMessage();
      $this->logger->error($msg);
      return $msg;
    }

    if (empty($prepayID)) {
      $msg = '微信支付创建失败';
      return $msg;
    }
    //预支付创建成功，接下来返回前端js调起支付
    $response = $this->getJavaScript($prepayID, $order['return_url']);
    return new Response($response);
  }

  /**
   * 返回调起微信支付的js代码
   *
   * @param $prepayId  string 预下单ID
   * @param $returnURL string 付款后的返回链接
   *
   * @return string 响应页面HTML内容
   * @see https://pay.weixin.qq.com/wiki/doc/apiv3/open/pay/chapter2_3.shtml#part-6
   */
  protected function getJavaScript($prepayId, $returnURL) {
    $appId = $this->config->get('appId');
    $timeStamp = time();
    $nonceStr = $this->getRandStr(32);
    $package = "prepay_id={$prepayId}";
    $paySign = $this->getSign("$appId\n$timeStamp\n$nonceStr\n$package\n");

    $js = <<<yunke
<!DOCTYPE html>
<html lang="zh-hans">
<head>
    <meta charset="UTF-8">
    <title>未来很美</title>
</head>
<body>
   <script type="text/javascript">
    function onBridgeReady() {
        WeixinJSBridge.invoke('getBrandWCPayRequest', {
                "appId": "{$appId}",   //公众号ID，由商户传入
                "timeStamp": "{$timeStamp}",   //时间戳，自1970年以来的秒数
                "nonceStr": "{$nonceStr}",      //随机串
                "package": "{$package}",
                "signType": "RSA",     //微信签名方式：
                "paySign": "{$paySign}" //微信签名
            },
            function (res) {
                //if (res.err_msg == "get_brand_wcpay_request:ok") {
                    // 使用以上方式判断前端返回,微信团队郑重提示：
                    //res.err_msg将在用户支付成功后返回ok，但并不保证它绝对可靠。
                    location.href="{$returnURL}";
                //}
            });
    }

    if (typeof WeixinJSBridge == "undefined") {
        if (document.addEventListener) {
            document.addEventListener('WeixinJSBridgeReady', onBridgeReady, false);
        } else if (document.attachEvent) {
            document.attachEvent('WeixinJSBridgeReady', onBridgeReady);
            document.attachEvent('onWeixinJSBridgeReady', onBridgeReady);
        }
    } else {
        onBridgeReady();
    }
   </script>
</body>
</html>
yunke;

    return $js;
  }

  /**
   * 得到接口加签随机字符串 用于JS支付
   *
   * @param $len
   *
   * @return string
   */
  private function getRandStr($len) {
    //随机字符串
    $str = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890';
    $nonce = '';
    $max = strlen($str) - 1;
    for ($i = 0; $i < $len; $i++) {
      $nonce .= $str[mt_rand(0, $max)];
    }
    return $nonce;
  }

  /**
   * 通过商户订单号查询订单状态
   *
   * @param $outTradeNo string 商户系统中保存的商户订单号  并非微信订单号
   *
   * @return array|bool 查询失败时返回false 否则返回信息数组（但并不表示业务成功，当订单不存在时也返回信息数组）
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_2.shtml
   */
  public function query($outTradeNo) {
    $url = $this->config->get('api.queryURL') ?: 'https://api.mch.weixin.qq.com/v3/pay/transactions/out-trade-no/';
    $url = $url . $outTradeNo . '?mchid=' . $this->config->get('merchantId');
    try {
      $resp = $this->client->request(
        'GET',
        $url, //请求URL
        [
          'headers' => ['Accept' => 'application/json'],
        ]
      );
      $statusCode = $resp->getStatusCode();
      if ($statusCode == 200) { //处理成功
        $json = json_decode($resp->getBody()->getContents());
        return (array) $json;
      }
      else {
        return FALSE;
      }
    } catch (RequestException $e) {
      // 进行错误处理
      $msg = $e->getMessage() . "\n";
      if ($e->hasResponse()) {
        $msg .= "订单查询失败，Status Code:" . $e->getResponse()->getStatusCode() . " return body:" . $e->getResponse()->getBody() . "\n";
      }
      $this->logger->warning($msg);
      return FALSE;
    }
  }

  /**
   * 订单退款接口
   *
   * @param $order array 退款参数数组 详见方法内说明
   *
   * @return array|bool 成功返回信息数组 失败返回false
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_9.shtml
   */
  public function refund($order) {
    /**
     * $order['order_number']
     * 必选 商户订单号 长度string[6,32] 商户系统内部订单号，只能是数字、大小写字母_-*且在同一个商户号下唯一
     * $order['refund_number']
     * 必选 退款单号  String  最大长度64
     * $order['refund_amount']
     * 必选 退款金额 单位分
     * $order['total']
     * 必选 原订单总金额 单位分
     * $order['refund_reason']
     * 可选 退款原因 String  最大长度256
     * $order['notify_url']
     * 可选 退款异步通知地址 string[1,256] 通知URL必须为直接可访问的URL，不允许携带查询串。
     */
    $config = $this->config;
    $body = [
      "out_trade_no"  => $order['order_number'],
      "out_refund_no" => $order['refund_number'],
      "amount"        => [ //必选 退款金额信息
        "refund"   => (int) $order['refund_amount'], //必选 退款金额 int，单位为分。
        "total"    => (int) $order['total'], //必选 退款金额 int，单位为分。
        "currency" => "CNY", //可选 货币类型标识 string[1,16] CNY：人民币，境内商户号仅支持人民币。
      ],
    ];
    if (!empty($order['refund_reason'])) {
      $body["reason"] = $order['refund_reason'];
    }
    if (!empty($order['notify_url'])) {
      $body["notify_url"] = $order['notify_url'];
    }

    $msg = NULL; //错误消息
    try {
      $resp = $this->client->request(
        'POST',
        $config->get('api.refundURL') ?: 'https://api.mch.weixin.qq.com/v3/refund/domestic/refunds', //请求URL
        [
          'json'    => $body,
          'headers' => ['Accept' => 'application/json'],
        ]
      );
      $statusCode = $resp->getStatusCode();
      if ($statusCode == 200) { //处理成功
        $json = json_decode($resp->getBody()->getContents());
        return (array) $json;
      }
      else {
        return FALSE;
      }
    } catch (RequestException $e) {
      // 进行错误处理
      $msg = '微信支付退款失败, ' . $e->getMessage() . "\n";
      if ($e->hasResponse()) {
        $msg .= "Status Code:" . $e->getResponse()
            ->getStatusCode() . "; Body:" . $e->getResponse()
            ->getBody() . "\n";
      }
      $this->logger->error($msg);
      return FALSE;
    }
  }

  /**
   * 订单退款查询
   *
   * @param $outRefundNo string 商户系统内部的退款单号
   *
   * @return array|bool 查询成功返回信息数组 失败返回false
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_4_10.shtml
   */
  public function queryRefund($outRefundNo) {
    $url = $this->config->get('api.queryRefundURL') ?: 'https://api.mch.weixin.qq.com/v3/refund/domestic/refunds/';
    $url = $url . $outRefundNo;
    try {
      $resp = $this->client->request(
        'GET',
        $url, //请求URL
        [
          'headers' => ['Accept' => 'application/json'],
        ]
      );
      $statusCode = $resp->getStatusCode();
      if ($statusCode == 200) { //处理成功
        $json = json_decode($resp->getBody()->getContents());
        return (array) $json;
      }
      else {
        return FALSE;
      }
    } catch (RequestException $e) {
      // 进行错误处理
      $msg = $e->getMessage() . "\n";
      if ($e->hasResponse()) {
        $msg .= "订单退款查询失败，Status Code:" . $e->getResponse()->getStatusCode() . " return body:" . $e->getResponse()->getBody() . "\n";
      }
      $this->logger->warning($msg);
      return FALSE;
    }
  }

  /**
   * 立即更新微信支付平台证书
   *
   * @return bool 是否更新成功
   */
  public function updateWechatCertificate() {
    $updater = new RefreshWechatCertificate();
    return $updater->refresh();
  }

  /**
   * 得到连接微信服务器的http客户端
   * 其中已经压入了中间件  会自动加签和验签等
   *
   * @return bool|\GuzzleHttp\Client
   * @see https://github.com/wechatpay-apiv3/wechatpay-guzzle-middleware
   * @see https://github.com/wechatpay-apiv3/wechatpay-php
   */
  public function getHTTPClient() {
    static $HTTPClient = NULL;
    if ($HTTPClient !== NULL) {
      return $HTTPClient;
    }
    $wechatConfig = $this->config;
    $wechatPayCertificate = $wechatConfig->get('wechatPayCertificate');
    if (!$wechatPayCertificate) {
      if ($this->updateWechatCertificate()) {
        \Drupal::configFactory()->reset('yunke_pay.WeChat');
        $this->config = $wechatConfig = \Drupal::config('yunke_pay.WeChat');
        $wechatPayCertificate = $wechatConfig->get('wechatPayCertificate');
      }
      else {
        $this->logger->error('can not get HTTPClient, Missing wechat platform certificate');
        return FALSE;
      }
    }

    /**
     * 非composer安装方式被弃用 现在必须使用composer安装
     * 如果是非composer安装 那么添加中间件到全局类加载器，以便自动加载腾讯中间件
     *
     * @todo 实验在此处直接引入模块的composer类加载器
     * @todo 这种方式不利于IDE开发 弃用
     * $class_loader = \Drupal::service('class_loader');
     * $path = \Drupal::moduleHandler()
     * ->getModule("yunke_pay")
     * ->getPath() . '/vendor/wechatpay/wechatpay-guzzle-middleware/src';
     * $class_loader->addPsr4('WechatPay\GuzzleMiddleware\\', $path);
     */

    // 商户相关配置
    $merchantId = $wechatConfig->get('merchantId'); // 商户号
    $merchantSerialNumber = $wechatConfig->get('merchantSerialNumber'); // 商户API证书序列号
    $merchantPrivateKey = PemUtil::loadPrivateKeyFromString($wechatConfig->get('merchantPrivateKey')); // 商户私钥

    // 微信支付平台配置
    $wechatPayCertificates = [];
    foreach ($wechatPayCertificate as $certificate) {
      $wechatPayCertificates[] = PemUtil::loadCertificateFromString($certificate['certificate']);// 微信支付平台证书
    }

    // 构造一个WechatPayMiddleware
    $wechatPayMiddleware = WechatPayMiddleware::builder()
      ->withMerchant($merchantId, $merchantSerialNumber, $merchantPrivateKey) // 传入商户相关配置
      ->withWechatPay($wechatPayCertificates) // 可传入多个微信支付平台证书，参数类型为array
      ->build();

    $client = \Drupal::service('http_client_factory')->fromOptions();
    $client->getConfig('handler')->push($wechatPayMiddleware, 'wechatpay');

    $HTTPClient = $client;
    return $HTTPClient;
  }

  /**
   * 判断客户端属于何种类型的支付场景 返回三种情况：
   * PC（电脑端网页）、WeChat（微信客户端）、Mobile（移动端网页）
   * 以此应用不同的支付方式
   *
   * @param null $userAgent 用户代理标识
   *
   * @return string PC|WeChat|Mobile
   */
  public function getUserAgentType($userAgent = NULL) {
    //常见用户代理：
    //火狐PC： Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:88.0) Gecko/20100101 Firefox/88.0
    //谷歌PC： Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36
    //微软PC： Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36 Edg/90.0.818.62
    //微信PC： Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.116 Safari/537.36 QBCore/4.0.1326.400 QQBrowser/9.0.2524.400 Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2875.116 Safari/537.36 NetType/WIFI MicroMessenger/7.0.20.1781(0x6700143B) WindowsWechat(0x63010200)


    //火狐移动：Mozilla/5.0 (Android 9; Mobile; rv:88.0) Gecko/88.0 Firefox/88.0
    //谷歌移动：
    //微软移动：Mozilla/5.0 (Linux; Android 9; MI 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.116 Mobile Safari/537.36 EdgA/45.09.4.5079
    //微信移动：Mozilla/5.0 (Linux; Android 9; MI 6 Build/PKQ1.190118.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/78.0.3904.62 XWEB/2797 MMWEBSDK/20210302 Mobile Safari/537.36 MMWEBID/1758 MicroMessenger/8.0.3.1880(0x28000339) Process/toolsmp WeChat/arm64 Weixin NetType/WIFI Language/zh_CN ABI/arm64
    //小米移动：Mozilla/5.0 (Linux; U; Android 9; zh-cn; MI 6 Build/PKQ1.190118.001) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/79.0.3945.147 Mobile Safari/537.36 XiaoMi/MiuiBrowser/14.4.18
    //QQ移动：  Mozilla/5.0 (Linux; U; Android 9; zh-cn; MI 6 Build/PKQ1.190118.001) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/77.0.3865.120 MQQBrowser/11.5 Mobile Safari/537.36 COVC/045429
    //夸克移动：Mozilla/5.0 (Linux; U; Android 9; zh-CN; MI 6 Build/PKQ1.190118.001) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/78.0.3904.108 Quark/4.9.0.176 Mobile Safari/537.36


    $userAgentType = 'PC'; //PC Mobile WeChat
    $userAgent = $userAgent ?: $_SERVER['HTTP_USER_AGENT'];
    if (stripos($userAgent, 'WeChat') !== FALSE && stripos($userAgent, 'Mobile') !== FALSE) {
      $userAgentType = 'WeChat';
    }
    elseif (stripos($userAgent, 'MicroMessenger') !== FALSE && stripos($userAgent, 'Mobile') !== FALSE) {
      $userAgentType = 'WeChat';
    }
    elseif (stripos($userAgent, 'Mobile') !== FALSE) {
      $userAgentType = 'Mobile';
    }
    return $userAgentType;
  }

  /**
   * 得到发送给微信的消息的签名值
   *
   * @param $message string 消息内容 参见本类的getJavaScript方法
   *
   * @return string 消息签名值
   */
  public function getSign($message) {
    $merchantSerialNumber = $this->config->get('merchantSerialNumber'); // 商户API证书序列号
    $merchantPrivateKey = PemUtil::loadPrivateKeyFromString($this->config->get('merchantPrivateKey')); // 商户私钥
    $signer = new PrivateKeySigner($merchantSerialNumber, $merchantPrivateKey);
    $signResult = $signer->sign($message);
    $sign = $signResult->getSign();
    return $sign;
  }

  /**
   * 订单付款、退款的异步通知验签
   *
   * @param \Symfony\Component\HttpFoundation\Request $request 来自\Drupal::request();
   *
   * @return bool 是否成功 true为验签成功 说明请求来自微信服务器  否则应该放弃处理
   * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_5.shtml
   * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_11.shtml
   */
  public function verifyNotify(Request $request) {
    $serialNo = $request->headers->get('Wechatpay-Serial');
    $sign = $request->headers->get('Wechatpay-Signature');
    $timestamp = $request->headers->get('Wechatpay-TimeStamp');
    $nonce = $request->headers->get('Wechatpay-Nonce');

    if (!isset($serialNo, $sign, $timestamp, $nonce)) {
      return FALSE;
    }
    if (!(\abs((int) $timestamp - \time()) <= 300)) { //时间差错超过5分钟的通知被放弃
      return FALSE;
    }
    $body = $request->getContent();
    $message = "$timestamp\n$nonce\n$body\n";
    $wechatPayCertificate = $this->config->get('wechatPayCertificate');
    // 微信支付平台公钥配置
    $wechatPayCertificates = [];
    foreach ($wechatPayCertificate as $certificate) {
      $wechatPayCertificates[] = PemUtil::loadCertificateFromString($certificate['certificate']);// 微信支付平台证书
    }
    $validator = new CertificateVerifier($wechatPayCertificates);
    try {
      $result = $validator->verify($serialNo, $message, $sign);
      return $result;
    } catch (\Exception $e) {
      $this->logger->error('无法处理微信支付签名验证，' . $e->getMessage());
      return FALSE;
    }
  }

  /**
   * 解密出付款、退款异步通知的内容
   *
   * @param \Symfony\Component\HttpFoundation\Request $request 来自\Drupal::request();
   *
   * @return array|bool 解密成功时返回信息数组 否则返回false
   * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_5.shtml
   * @see https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_11.shtml
   */
  public function decrypt(Request $request) {
    $body = json_decode($request->getContent());
    if (isset($body->resource->ciphertext)) {//解密出通知的json数据
      $key = $this->config->get('apiV3SecretKey');
      $aes = new AesUtil($key);
      $associatedData = $body->resource->associated_data;
      $nonceStr = $body->resource->nonce;
      $ciphertext = $body->resource->ciphertext;
      $result = $aes->decryptToString($associatedData, $nonceStr, $ciphertext);
      $result = (array) json_decode($result);
      return $result;
    }
    else {
      return FALSE;
    }
  }

  /**
   * 在微信支付的JSAPI中（也就是在微信浏览器中支付时），下单要求有用户的openid
   * 而这需要做一个用户授权操作才能拿到，该方法即是用来返回授权链接的
   *
   * @param $redirectURL string 授权后的重定向链接
   *
   * @return string
   */
  public function getOauth2URL($redirectURL) {
    $appid = $this->config->get('appId');
    $redirectURL = \urlencode($redirectURL);
    $oauth2URL = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $appid . "&redirect_uri=" . $redirectURL . "&response_type=code&scope=snsapi_base&state=STATE#wechat_redirect";
    return $oauth2URL;
  }

  /**
   * 用授权码code去取回用户的OpenId
   *
   * @param $code string 授权码
   *
   * @return bool | string
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getOpenId($code) {
    $appid = $this->config->get('appId');
    $secret = $this->config->get('appSecretKey');
    $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . $appid . "&secret=" . $secret . "&code=" . $code . "&grant_type=authorization_code";
    try {
      $resp = \Drupal::httpClient()->request( //这属于公众号功能，不用微信支付客户端对象
        'GET',
        $url,
        [
          'headers' => ['Accept' => 'application/json'],
        ]
      );
      $statusCode = $resp->getStatusCode(); //return $statusCode.''.$resp->getBody()->getContents();
      if ($statusCode == 200) { //处理成功
        $json = json_decode($resp->getBody()->getContents());
        if (!empty($json->openid)) {
          return $json->openid;
        }
        else {
          return FALSE;
        }
      }
      else {
        return FALSE;
      }
    } catch (RequestException $e) {
      // 进行错误处理
      $msg = $e->getMessage() . "\n";
      if ($e->hasResponse()) {
        $msg .= "获取用户openID错误, Status Code:" . $e->getResponse()
            ->getStatusCode() . "; Body:" . $e->getResponse()
            ->getBody() . "\n";
      }
      $this->logger->error($msg);
      return FALSE;
    }
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

  /**
   * 返回有期限控制的键值储存对象 使用时不用调用保存，也不用考虑数据清理 系统会自行处理
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  public function getStorage() {
    $collection = 'yunke_pay';
    return \Drupal::service("keyvalue.expirable.database")->get($collection);
    //$storage->setWithExpire($key, $value, $expire); $expire是有效期，单位秒，不是时间戳
    //$storage->get($key, $default = NULL);
  }

}
