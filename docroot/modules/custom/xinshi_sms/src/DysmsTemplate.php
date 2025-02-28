<?php


namespace Drupal\xinshi_sms;


class DysmsTemplate {
  /**
   * @var string  访问秘钥
   */
  private $accessKeyId;

  /**
   * @var string 安全秘钥
   */
  private $accessKeySecret;

  /**
   * @var string 模板编号
   */
  private $templateCode;

  /**
   * @var string 签名
   */
  private $signName;

  /**
   * DysmsTemplate constructor.
   * @param $access_key_id
   * @param $access_key_secret
   * @param $sign_name
   * @param $template_code
   */
  public function __construct($access_key_id, $access_key_secret, $sign_name, $template_code) {
    $this->setAccessKeyId($access_key_id);
    $this->setAccessKeySecret($access_key_secret);
    $this->setSignName($sign_name);
    $this->setTemplateCode($template_code);
  }

  /**
   * @return string
   */
  public function getAccessKeyId(): string {
    return $this->accessKeyId;
  }

  /**
   * @param string $accessKeyId
   */
  public function setAccessKeyId(string $accessKeyId): void {
    $this->accessKeyId = $accessKeyId;
  }

  /**
   * @return string
   */
  public function getAccessKeySecret(): string {
    return $this->accessKeySecret;
  }

  /**
   * @param string $accessKeySecret
   */
  public function setAccessKeySecret(string $accessKeySecret): void {
    $this->accessKeySecret = $accessKeySecret;
  }

  /**
   * @return string
   */
  public function getTemplateCode(): string {
    return $this->templateCode;
  }

  /**
   * @param string $templateCode
   */
  public function setTemplateCode(string $templateCode): void {
    $this->templateCode = $templateCode;
  }

  /**
   * @return string
   */
  public function getSignName(): string {
    return $this->signName;
  }

  /**
   * @param string $signName
   */
  public function setSignName(string $signName): void {
    $this->signName = $signName;
  }

}
