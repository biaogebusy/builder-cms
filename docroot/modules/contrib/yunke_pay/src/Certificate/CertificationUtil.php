<?php
/**
 * 修改阿里证书实用工具 以便于从数据库读取证书
 * 开发者: yunke
 * Email: phpworld@qq.com
 */

namespace Drupal\yunke_pay\Certificate;

use Alipay\EasySDK\Kernel\Util\AntCertificationUtil;

class CertificationUtil extends AntCertificationUtil {

  protected $rootCertContent;

  /**
   * 从证书中提取序列号
   *
   * @param $cert
   *
   * @return string
   */
  public function getCertSN($cert) {
    //$cert = file_get_contents($certPath); //从数据库读取
    $ssl = openssl_x509_parse($cert);
    $SN = md5($this->array2string(array_reverse($ssl['issuer'])) . $ssl['serialNumber']);
    return $SN;
  }

  /**
   * 从证书中提取公钥
   *
   * @param $cert
   *
   * @return mixed
   */
  public function getPublicKey($cert) {
    //$cert = file_get_contents($certPath); //从数据库读取
    $pkey = openssl_pkey_get_public($cert);
    $keyData = openssl_pkey_get_details($pkey);
    $public_key = str_replace('-----BEGIN PUBLIC KEY-----', '', $keyData['key']);
    $public_key = trim(str_replace('-----END PUBLIC KEY-----', '', $public_key));
    return $public_key;
  }

  /**
   * 提取根证书序列号
   *
   * @param $cert  string 根证书
   *
   * @return string|null
   */
  public function getRootCertSN($cert) {
    //$cert = file_get_contents($certPath); //从数据库读取
    $this->rootCertContent = $cert;
    $array = explode("-----END CERTIFICATE-----", $cert);
    $SN = NULL;
    for ($i = 0; $i < count($array) - 1; $i++) {
      $ssl[$i] = openssl_x509_parse($array[$i] . "-----END CERTIFICATE-----");
      if (strpos($ssl[$i]['serialNumber'], '0x') === 0) {
        $ssl[$i]['serialNumber'] = $this->hex2dec($ssl[$i]['serialNumber']);
      }
      if ($ssl[$i]['signatureTypeLN'] == "sha1WithRSAEncryption" || $ssl[$i]['signatureTypeLN'] == "sha256WithRSAEncryption") {
        if ($SN == NULL) {
          $SN = md5($this->array2string(array_reverse($ssl[$i]['issuer'])) . $ssl[$i]['serialNumber']);
        }
        else {

          $SN = $SN . "_" . md5($this->array2string(array_reverse($ssl[$i]['issuer'])) . $ssl[$i]['serialNumber']);
        }
      }
    }
    return $SN;
  }

}
