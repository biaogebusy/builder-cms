<?php

namespace Drupal\xinshi_sms\Plugin\SmsGateway;

use AlibabaCloud\SDK\Dypnsapi\V20170525\Models\SendSmsVerifyCodeResponse;
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
use AlibabaCloud\SDK\Dypnsapi\V20170525\Dypnsapi;
use AlibabaCloud\SDK\Dypnsapi\V20170525\Models\SendSmsVerifyCodeRequest;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;

/**
 * Defines a alibaba send sms.
 *
 * @SmsGateway(
 *   id = "alibaba_dypnsapi_send",
 *   label = @Translation("阿里云 发送短信验证码 融合认证（基于原子能力）"),
 *   outgoing_message_max_recipients = -2,
 * )
 */
class AlibabaDypnsapiSend extends SmsGatewayPluginBase implements ContainerFactoryPluginInterface {


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
      $message = $this->sendDypnsapi($number, $sms->getMessage());
      $report = (new SmsDeliveryReport())
        ->setRecipient($number)
        ->setStatus(SmsMessageReportStatus::DELIVERED)
        ->setStatusMessage($message)
        ->setTimeDelivered(REQUEST_TIME);
      $result->addReport($report);
    }

    return $result;
  }

  /**
   * 使用云通信方式
   * @param $number
   * @param $code
   * @return string
   */
  protected function sendDypnsapi($number, $code) {
    $client = $this->createClient();
    $params = [
      "phoneNumber" => $number,
      "signName" => $this->configuration['sign_name'],
      "templateCode" => $this->configuration['template_code'],
      "templateParam" => json_encode(['code' => $code, 'min' => 5]),
    ];
    $sendSmsVerifyCodeRequest = new SendSmsVerifyCodeRequest($params);
    $runtime = new RuntimeOptions([]);
    try {
      // 复制代码运行请自行打印 API 的返回值
      /** @var SendSmsVerifyCodeResponse $rest */
      $sms_result = $client->sendSmsVerifyCodeWithOptions($sendSmsVerifyCodeRequest, $runtime);
      if ($sms_result->body->success) {
        return $sms_result->body->message;
      } else {
        $this->logger->warning('Alibaba SMS message sent to %number failed: @message', [
          '%number' => $number,
          '@message' => $sms_result->body->message,
        ]);
        return $sms_result->body->message;
      }

    } catch (\Exception $error) {
      $this->logger->error('Alibaba SMS message sent to %number failed: @message', [
        '%number' => $number,
        '@message' => $error->getMessage(),
      ]);
      return $error->getMessage();
    }
  }

  /**
   * 使用凭据初始化账号Client
   * @return Dypnsapi Client
   */
  protected function createClient() {
    // 工程代码建议使用更安全的无AK方式，凭据配置方式请参见：https://help.aliyun.com/document_detail/311677.html。
    $config = new Config([
      'type' => 'access_key',
      // AccessKey ID
      "accessKeyId" => $this->configuration['access_key_id'],
      // AccessKey Secret
      "accessKeySecret" => $this->configuration['access_key_secret'],
    ]);

    // Endpoint 请参考 https://api.aliyun.com/product/Dypnsapi
    $config->endpoint = "dypnsapi.aliyuncs.com";
    return new Dypnsapi($config);
  }

}
