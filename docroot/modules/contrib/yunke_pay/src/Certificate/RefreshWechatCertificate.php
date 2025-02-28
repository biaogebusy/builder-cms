<?php

/**
 *  由于微信支付的平台证书会不定期更新 且只能通过API接口获取 因此需要自动处理
 *  本类获取及更新微信支付平台证书
 *  by:yunke
 *  email:phpworld@qq.com
 *  time:20210505
 */

namespace Drupal\yunke_pay\Certificate;

use WechatPay\GuzzleMiddleware\WechatPayMiddleware;
use WechatPay\GuzzleMiddleware\Util\PemUtil;
use Drupal\yunke_pay\Certificate\NoopValidator;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client;
use WechatPay\GuzzleMiddleware\Util\AesUtil;
use WechatPay\GuzzleMiddleware\Auth\WechatPay2Validator;
use WechatPay\GuzzleMiddleware\Auth\CertificateVerifier;
use GuzzleHttp\Exception\RequestException;

class RefreshWechatCertificate {

  //可编辑配置对象，用于保存获取的微信支付平台证书
  protected $wechatConfig = NULL;

  //日志服务
  protected $logger = NULL;

  //平台证书API接口
  protected $apiURL = 'https://api.mch.weixin.qq.com/v3/certificates';


  public function __construct() {
    $this->wechatConfig = \Drupal::configFactory()
      ->getEditable('yunke_pay.WeChat');
    $this->logger = \Drupal::logger('yunke_pay');
    $apiURL = $this->wechatConfig->get('certificatesURL'); //平台证书API接口
    if ($apiURL) {
      $this->apiURL = $apiURL;
    }
  }


  public function refresh() {
    $message = '';//日志消息
    try {

      // 商户相关配置
      $merchantId = $this->wechatConfig->get('merchantId'); // 商户号
      $merchantSerialNumber = $this->wechatConfig->get('merchantSerialNumber'); // 商户API证书序列号
      $merchantPrivateKey = PemUtil::loadPrivateKeyFromString($this->wechatConfig->get('merchantPrivateKey')); // 商户私钥

      // 构造一个WechatPayMiddleware
      $builder = WechatPayMiddleware::builder()
        ->withMerchant($merchantId, $merchantSerialNumber, $merchantPrivateKey); // 传入商户相关配置

      /*
      //@todo 是否考虑用被保存的证书去验证签名？有必要吗？
      if (isset($opts['wechatpay-cert'])) {
        $builder->withWechatPay([ PemUtil::loadCertificate($opts['wechatpay-cert']) ]); // 使用平台证书验证
      }
      else {
        $builder->withValidator(new NoopValidator); // 临时"跳过”应答签名的验证
      }
      */

      $builder->withValidator(new NoopValidator); // 临时"跳过”应答签名的验证
      $wechatpayMiddleware = $builder->build();

      // 将WechatPayMiddleware添加到Guzzle的HandlerStack中
      $stack = HandlerStack::create();
      $stack->push($wechatpayMiddleware, 'wechatpay');
      // 创建Guzzle HTTP Client时，将HandlerStack传入
      $client = new Client(['handler' => $stack]);

      // 接下来，正常使用Guzzle发起API请求，WechatPayMiddleware会自动地处理签名和验签
      $resp = $client->request('GET', $this->apiURL, [
        'headers' => ['Accept' => 'application/json'],
      ]);
      if ($resp->getStatusCode() < 200 || $resp->getStatusCode() > 299) {
        $message = "wechat platform certificates download failed, code={$resp->getStatusCode()}, body=[{$resp->getBody()}]\n";
        $this->logger->error($message);
        return FALSE;
      }

      $list = json_decode($resp->getBody(), TRUE);

      $plainCerts = [];
      $x509Certs = [];

      $decrypter = new AesUtil($this->wechatConfig->get('apiV3SecretKey')); //api v3 密钥
      foreach ($list['data'] as $item) {
        $encCert = $item['encrypt_certificate'];
        $plain = $decrypter->decryptToString($encCert['associated_data'], $encCert['nonce'], $encCert['ciphertext']);
        if (!$plain) {
          $message = "encrypted wechat platform certificate decrypt fail!\n";
          $this->logger->error($message);
          return FALSE;
        }
        // 通过加载对证书进行简单合法性检验
        $cert = \openssl_x509_read($plain); // 从字符串中加载证书
        if (!$cert) {
          $message = "downloaded wechat platform certificate check fail!\n";
          $this->logger->error($message);
          return FALSE;
        }
        $plainCerts[] = $plain;
        $x509Certs[] = $cert;
      }
      // 使用下载的证书再来验证一次应答的签名
      $validator = new WechatPay2Validator(new CertificateVerifier($x509Certs));
      if (!$validator->validate($resp)) {
        $message = "validate response fail using downloaded wechat platform certificates!"; //发生了中间人攻击
        $this->logger->error($message);
        return FALSE;
      }
      $certificates = [];
      // 提取证书信息
      foreach ($list['data'] as $index => $item) {
        $certificate = [];
        $certificate['serial_no'] = $item['serial_no'];
        $certificate['effective_time'] = $item['effective_time'];
        $certificate['expire_time'] = $item['expire_time'];
        $certificate['certificate'] = $plainCerts[$index];
        $certificates[] = $certificate;
      }
      $this->wechatConfig->set('wechatPayCertificate', $certificates);
      $this->wechatConfig->set('wechatPayCertificateTime', time());
      $this->wechatConfig->save();
      $message = "wechat platform certificates update successfully !";
      $this->logger->info($message);
      return TRUE;

    } catch (RequestException $e) {
      $message = "wechat platform certificates download failed, message=[{$e->getMessage()}] \n";
      if ($e->hasResponse()) {
        $message .= "code={$e->getResponse()->getStatusCode()}, body=[{$e->getResponse()->getBody()}]\n";
      }
      $this->logger->error($message);
      return FALSE;
    } catch (\Exception $e) {
      $message = "wechat platform certificates download failed, message=[{$e->getMessage()}]\n";
      $this->logger->error($message);
      return FALSE;
    }

  }


}

