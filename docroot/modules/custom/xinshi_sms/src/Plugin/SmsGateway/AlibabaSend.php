<?php

namespace Drupal\xinshi_sms\Plugin\SmsGateway;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\sms\Message\SmsDeliveryReport;
use Drupal\sms\Message\SmsMessageReportStatus;
use Drupal\sms\Plugin\SmsGatewayPluginBase;
use Drupal\sms\Message\SmsMessageInterface;
use Drupal\sms\Message\SmsMessageResult;
use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Dysmsapi;
use AlibabaCloud\SDK\Dysmsapi\V20170525\Models\SendSmsRequest;

/**
 * Defines a alibaba send sms.
 *
 * @SmsGateway(
 *   id = "alibaba_send",
 *   label = @Translation("Alibaba send"),
 *   outgoing_message_max_recipients = -2,
 * )
 */
class AlibabaSend extends SmsGatewayPluginBase implements ContainerFactoryPluginInterface {

  /** @var Dysmsapi */
  private $client;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $settings;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a LogGateway object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The logger factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $definition = $this->getPluginDefinition();
    $this->logger = $logger_factory->get($definition['provider'] . '.' . $definition['id']);
    $this->settings = $config_factory->get('xinshi_sms.settings')->get('alibaba');
    $config = new Config([
      // AccessKey ID
      "accessKeyId" => $this->settings['access_key_id'] ?? '',
      // AccessKey Secret
      "accessKeySecret" => $this->settings['access_key_secret'] ?? '',
    ]);
    // 访问的域名
    $config->endpoint = "dysmsapi.aliyuncs.com";
    $this->client = new Dysmsapi($config);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function send(SmsMessageInterface $sms) {
    $result = new SmsMessageResult();
    foreach ($sms->getRecipients() as $number) {
      $param['code'] = $sms->getMessage();
      $sms_request = new SendSmsRequest([
        "phoneNumbers" => $number,
        "templateCode" => $this->settings['template_code'],
        "templateParam" => json_encode($param),
        "signName" => $this->settings['sign_name'],
      ]);
      $sms_result = $this->client->sendSms($sms_request);
      $report = (new SmsDeliveryReport())
        ->setRecipient($number)
        ->setStatus(SmsMessageReportStatus::DELIVERED)
        ->setStatusMessage($sms_result->body->message)
        ->setTimeDelivered(REQUEST_TIME);
      $result->addReport($report);
      if ($sms_result->body->code != 'OK') {
        $this->logger->warning('Alibaba SMS message sent to %number failed: @message', [
          '%number' => $number,
          '@message' => $sms_result->body->message,
        ]);
      } else {
        $this->logger->notice('Alibaba SMS message sent to %number success.', [
          '%number' => $number,
        ]);
      }
    }

    return $result;
  }

}
