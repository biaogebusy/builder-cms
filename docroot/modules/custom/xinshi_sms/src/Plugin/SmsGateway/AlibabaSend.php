<?php

namespace Drupal\xinshi_sms\Plugin\SmsGateway;

use Drupal\Core\Form\FormStateInterface;
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
 *   label = @Translation("阿里云短信服务"),
 *   outgoing_message_max_recipients = -2,
 * )
 */
class AlibabaSend extends SmsGatewayPluginBase implements ContainerFactoryPluginInterface {

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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['sms'] = [
      "#tree" => TRUE,
      '#type' => 'details',
      '#title' => t('SMS Settings'),
      '#collapsible' => TRUE,
      '#open' => TRUE,
    ];
    $form['sms']['sign_name'] = [
      '#type' => 'textfield',
      '#title' => t('Sign'),
      '#default_value' => $this->configuration['sign_name'] ?? '',
      '#required' => TRUE,
    ];

    $form['sms']['template_code'] = [
      '#type' => 'textfield',
      '#title' => t('Template Code'),
      '#default_value' => $this->configuration['template_code'] ?? '',
      '#required' => TRUE,
    ];

    $form['sms']['access_key_id'] = [
      '#type' => 'textfield',
      '#title' => t('AccessKey ID'),
      '#default_value' => $this->configuration['access_key_id'] ?? '',
      '#required' => TRUE,
    ];

    $form['sms']['access_key_secret'] = [
      '#type' => 'textfield',
      '#title' => t('AccessKey Secret'),
      '#default_value' => $this->configuration['access_key_secret'] ?? '',
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['sign_name'] = $form_state->getValue(['sms', 'sign_name']);
    $this->configuration['template_code'] = $form_state->getValue(['sms', 'template_code']);
    $this->configuration['access_key_id'] = $form_state->getValue(['sms', 'access_key_id']);
    $this->configuration['access_key_secret'] = $form_state->getValue(['sms', 'access_key_secret']);
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function send(SmsMessageInterface $sms) {
    $result = new SmsMessageResult();
    foreach ($sms->getRecipients() as $number) {
      $message = $this->sendDysmsapi($number, $sms->getMessage());
      $report = (new SmsDeliveryReport())
        ->setRecipient($number)
        ->setStatus(SmsMessageReportStatus::DELIVERED)
        ->setStatusMessage($message)
        ->setTimeDelivered(\Drupal::time()->getRequestTime());
      $result->addReport($report);
    }

    return $result;
  }

  /**
   * 使用签名方式
   * @param $number
   * @param $code
   * @return string
   */
  protected function sendDysmsapi($number, $code) {
    $config = new Config([
      // AccessKey ID
      "accessKeyId" => $this->configuration['access_key_id'],
      // AccessKey Secret
      "accessKeySecret" => $this->configuration['access_key_secret'],
    ]);
    // 访问的域名
    $config->endpoint = "dysmsapi.aliyuncs.com";
    $client = new Dysmsapi($config);
    $param['code'] = $code;
    $sms_request = new SendSmsRequest([
      "phoneNumbers" => $number,
      "templateCode" => $this->configuration['template_code'],
      "templateParam" => json_encode($param),
      "signName" => $this->configuration['sign_name'],
    ]);
    $sms_result = $client->sendSms($sms_request);
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
    return $sms_result->body->message;
  }
}
