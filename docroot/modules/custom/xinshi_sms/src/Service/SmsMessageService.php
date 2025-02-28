<?php


namespace Drupal\xinshi_sms\Service;


use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;
use Darabonba\OpenApi\Models\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\xinshi_sms\DysmsTemplate;

/**
 * Class SmsMessageService
 * @package Drupal\xinshi_sms\Service
 */
class SmsMessageService {

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Domain redirect configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * SmsMessageService constructor.
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param ConfigFactoryInterface $config_factory
   * @param LoggerChannelFactoryInterface $logger_channel_factory
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_channel_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->config = $config_factory->get('xinshi_sms.settings');
    $this->logger = $logger_channel_factory->get('Xinshi SMS');
  }

  /**
   * 发送阿里云短信
   * @param $number
   * @param $parameter
   * @param DysmsTemplate|null $template
   * @return bool
   */
  public function aliyuncsSend($number, $parameter, DysmsTemplate $template = NULL) {
    if (empty($number)) {
      return FALSE;
    }
    if (empty($template)) {
      $template = $this->getDysmsTemplate();
    }
    $sms_request = new SendSmsRequest([
      "phoneNumbers" => $number,
      "templateCode" => $template->getTemplateCode(),
      "templateParam" => json_encode($parameter),
      "signName" => $template->getSignName(),
    ]);
    $sms_config = new Config([
      // AccessKey ID
      "accessKeyId" => $template->getAccessKeyId(),
      // AccessKey Secret
      "accessKeySecret" => $template->getAccessKeySecret(),
    ]);

    // 访问的域名
    $sms_config->endpoint = "dysmsapi.aliyuncs.com";
    $client = new Dysmsapi($sms_config);
    $sms_result = $client->sendSms($sms_request);
    if ($sms_result->body->code != 'OK') {
      $this->logger->warning('SMS message sent to %number failed: @message', [
        '%number' => $number,
        '@message' => $sms_result->body->message,
      ]);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * 获取阿里云短信模板
   * @return DysmsTemplate
   */
  public function getDysmsTemplate() {
    $settings = $this->config->get('alibaba');
    return new DysmsTemplate(
      $settings['access_key_id']
      , $settings['access_key_secret']
      , $settings['sign_name']
      , $settings['template_code']);
  }
}
