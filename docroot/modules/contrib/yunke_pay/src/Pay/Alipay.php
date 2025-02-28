<?php
/**
 * 本类封装了支付宝支付接口相关操作，开发前需要进行正确的接口配置，上层订单系统直接调用本类即可
 * 虽然本类已经封装了全部操作  但开发者仍然需要理解支付宝支付返回数据的含义才能进行订单系统的开发
 * 因此本类及其方法均列出了参考文档以供快速查阅
 *
 * 支付宝支付文档：https://opendocs.alipay.com/apis/api_1/alipay.trade.pay
 * 支付宝首页：https://www.alipay.com/
 *
 * 开发者: yunke
 * Email: phpworld@qq.com
 *
 * 为了保证安全 本类没有采用任何第三方封装件 仅使用阿里公司官方提供的SDK
 */

namespace Drupal\yunke_pay\Pay;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Alipay\EasySDK\Kernel\Factory;
use Alipay\EasySDK\Kernel\Config;
use Drupal\yunke_pay\Certificate\CertificationUtil;

class Alipay {

  //支付宝接口配置
  protected $config;

  //日志记录器
  protected $logger;


  public function __construct(ConfigFactoryInterface $configFactory, LoggerChannelFactoryInterface $channelFactory) {
    $this->config = $configFactory->get('yunke_pay.Alipay');
    $this->logger = $channelFactory->get('yunke_pay');
    $this->iniFactory();
  }

  /**
   * 调用支付宝接口下单付款
   *
   * @param array $order 一个被精简过的订单信息数组，包含六项信息：金额、描述、订单号、异步通知链接、跳回链接、超时时间
   *
   * @return \Symfony\Component\HttpFoundation\Response 返回一个会自动提交的表单 然后借助浏览器提交跳转到支付宝付款页面
   * @see https://opendocs.alipay.com/apis/api_1/alipay.trade.wap.pay  手机下单
   * @see https://opendocs.alipay.com/apis/api_1/alipay.trade.page.pay  电脑端PC下单
   */
  public function order($order = []) {
    /**
     * $order['order_number']
     * 必选 商户订单号 长度string[6,32] 商户系统内部订单号，只能是数字、大小写字母_-*且在同一个商户号下唯一
     * $order['description']
     * 必选 商品描述 长度string[1,127]
     * $order['total']
     * 必选 总金额 int 订单总金额，单位为元。
     * $order['notify_url']
     * 必选 通知地址 string[1,256] 通知URL必须为直接可访问的URL，不允许携带查询串。
     * $order['return_url']
     * 必选 返回链接 付款完成后重定向的链接
     * $order['timeout_express']
     * 必选 超时时间戳 超过该时间交易将无法下单
     */

    $subject = $order['description'];
    $outTradeNo = $order['order_number'];
    $totalAmount = $order['total'];
    $returnUrl = $order['return_url'];
    $notifyUrl = $order['notify_url'];

    //交易关闭时间
    $timeout_express = $order['timeout_express'] - time();
    if ($timeout_express <= 60) {
      $timeout_express = '1m'; //最小范围一分钟
    }
    elseif ($timeout_express > 60 && $timeout_express <= 86400) { //一天以内用分钟表示
      $timeout_express = ceil($timeout_express / 60) . 'm';
    }
    else {
      $timeout_express = ceil($timeout_express / 3600) . 'h'; //超过一天用小时表示
    }

    $userAgentType = $this->getUserAgentType();

    if ($userAgentType == 'Mobile') {
      $wapClient = Factory::payment()->wap();
      $wapClient->asyncNotify($notifyUrl);
      $wapClient->optional('timeout_express', $timeout_express);
      $result = $wapClient->pay($subject, $outTradeNo, $totalAmount, $returnUrl, $returnUrl);
    }
    else {
      //移动优先 不能判断客户端类型的按PC端浏览器处理 以便兼容PC时代的历史
      $pageClient = Factory::payment()->page();
      $pageClient->asyncNotify($notifyUrl);
      $pageClient->optional('timeout_express', $timeout_express);
      $result = $pageClient->pay($subject, $outTradeNo, $totalAmount, $returnUrl);
    }

    //返回一个会自动提交的表单 然后借助浏览器提交跳转到支付宝付款页面
    return new Response($result->body);
  }

  /**
   * 通过商户订单号查询订单状态
   *
   * @param $outTradeNo string 商户系统中保存的商户订单号  并非支付宝订单号
   *
   * @return array|bool 查询失败时返回false 否则返回信息数组（但并不表示业务成功，当订单不存在时也返回信息数组）
   * @see https://opendocs.alipay.com/apis/api_1/alipay.trade.query
   */
  public function query($outTradeNo) {
    try {
      $alipayTradeQueryResponse = Factory::payment()->common()->query($outTradeNo);
      return $alipayTradeQueryResponse->toMap();
    } catch (\Exception $e) {
      $this->logger->error('支付宝查询订单状态失败:' . $e->getMessage());
      return FALSE; //查询失败
    }
  }

  /**
   * 退款查询接口
   *
   * @param $outTradeNo    string 商户系统中保存的商户订单号  并非支付宝订单号
   * @param $outRequestNo  string 退款单号  唯一标识订单的每一笔退款 在订单号下唯一
   *
   * @return array|bool 查询失败时返回false 否则返回信息数组
   * @see https://opendocs.alipay.com/apis/api_1/alipay.trade.fastpay.refund.query
   */
  public function queryRefund($outTradeNo, $outRequestNo) {
    try {
      $response = Factory::payment()->common()->queryRefund($outTradeNo, $outRequestNo);
      return $response->toMap();
    } catch (\Exception $e) {
      $this->logger->error('支付宝退款查询失败:' . $e->getMessage());
      return FALSE; //查询失败
    }
  }

  /**
   * 发起退款
   *
   * @param array $order 退款单信息数组 详见方法内解释
   *
   * @return array|bool 失败时返回false 否则返回信息数组（但并不表示业务成功，当订单不存在时也返回信息数组）
   * @see https://opendocs.alipay.com/apis/api_1/alipay.trade.refund
   */
  public function refund($order = []) {
    /**
     * $order['out_trade_no'] //必选 商户订单号
     * $order['refund_amount'] //必选 退款金额 单位元，可精确到两位小数
     * $order['refund_reason'] //可选 退款原因 String  最大长度256
     * $order['out_request_no'] //按条件可选 退款单号  String  最大长度64 不选时为全部退款  如果是分多次或部分退款则必选
     */
    try {
      if (empty($order['out_trade_no']) || empty($order['refund_amount'])) {
        return FALSE;
      }
      $outTradeNo = $order['out_trade_no'];
      $refundAmount = $order['refund_amount'];
      $client = Factory::payment()->common();
      unset($order['out_trade_no'], $order['refund_amount']);
      foreach ($order as $k => $v) {
        if (!empty($v)) {
          $client->optional($k, $v);
        }
      }
      $alipayTradeQueryResponse = $client->refund($outTradeNo, $refundAmount);
      return $alipayTradeQueryResponse->toMap();
    } catch (\Exception $e) {
      $this->logger->error('支付宝订单退款失败:' . $e->getMessage());
      return FALSE; //查询失败
    }
  }

  /**
   * 付款异步通知验签
   * 此处的$parameters完整传入收到的$_POST即可，不必去除sign、sign_type等键
   * 详细签名逻辑见：\Alipay\EasySDK\Kernel\Util\Signer::verifyParams
   *
   * @param $parameters array
   *
   * @return bool 签名验证是否成功
   */
  public function verifyNotify($parameters) {
    return Factory::payment()->common()->verifyNotify($parameters);
  }

  /**
   * 初始化SDK 注入配置参数 仅需一次即可
   */
  protected function iniFactory() {
    $options = new Config();
    $options->protocol = $this->config->get('protocol'); //'https';
    $options->gatewayHost = $this->config->get('gatewayHost'); //'openapi.alipay.com'
    $options->signType = $this->config->get('signType'); //'RSA2'

    $options->appId = $this->config->get('appId');
    //'<-- 请填写您的AppId，例如：2019022663440152 -->'

    $options->merchantPrivateKey = $this->config->get('merchantPrivateKey');
    // 应用私钥 储存在数据库中，例如：MIIEvQIBADANB ... ...

    //如果证书文件是储存在文件中，那么需要设置以下三项，但我们从数据库读取，因而无需设置
    //$options->alipayCertPath = '<-- 请填写您的支付宝公钥证书文件路径，例如：/foo/alipayCertPublicKey_RSA2.crt -->';
    //$options->alipayRootCertPath = '<-- 请填写您的支付宝根证书文件路径，例如：/foo/alipayRootCert.crt" -->';
    //$options->merchantCertPath = '<-- 请填写您的应用公钥证书文件路径，例如：/foo/appCertPublicKey_2019051064521003.crt -->';
    $merchantCert = $this->config->get('merchantCert'); //商户应用证书
    $alipayRootCert = $this->config->get('alipayRootCert'); //支付宝根证书
    $alipayCert = $this->config->get('alipayCert'); //支付宝公钥证书
    $certificationUtil = new CertificationUtil();
    $options->merchantCertSN = $certificationUtil->getCertSN($merchantCert);
    $options->alipayRootCertSN = $certificationUtil->getRootCertSN($alipayRootCert);
    $options->alipayPublicKey = $certificationUtil->getPublicKey($alipayCert);


    //如果支付宝接口采用非证书模式，则无需赋值上面的三个证书内容，改为赋值如下的支付宝公钥字符串即可，为了安全我们统一采用证书模式
    //$options->alipayPublicKey = '<-- 请填写您的支付宝公钥，例如：MIIBIjANBg... -->';

    //设置通用的异步通知接收服务地址（可选），我们让订单系统在下单时随订单信息传递来设置它，因此这里跳过
    //$options->notifyUrl = 'https://pay.will-nice.com/yunke-pay/test-1';

    //可设置AES密钥，调用AES加解密相关接口时需要（可选）
    //$options->encryptKey = "<-- 请填写您的AES密钥，例如：aa4BtZ4tspm2wnXLb1ThQA== -->";

    //设置参数（全局只需设置一次）
    Factory::setOptions($options);
  }

  /**
   * 判断客户端属于何种类型 返回两种情况：
   * PC（电脑端网页）、Mobile（移动端网页）
   * 以此应用不同的支付方式
   *
   * @param null $userAgent 用户代理标识
   *
   * @return string 支付场景标识 PC|Mobile
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
    if (stripos($userAgent, 'Mobile') !== FALSE) {
      $userAgentType = 'Mobile';
    }
    return $userAgentType;
  }

}
