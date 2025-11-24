<?php

declare(strict_types = 1);

namespace Drupal\sms\Plugin\SmsGateway;

use Drupal\Component\Datetime\TimeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\sms\Message\SmsDeliveryReport;
use Drupal\sms\Message\SmsMessageReportStatus;
use Drupal\sms\Plugin\SmsGatewayPluginBase;
use Drupal\sms\Message\SmsMessageInterface;
use Drupal\sms\Message\SmsMessageResult;

/**
 * Defines a logger gateway for testing and debugging.
 *
 * @SmsGateway(
 *   id = \Drupal\sms\Plugin\SmsGateway\LogGateway::PLUGIN_ID,
 *   label = @Translation("Drupal log"),
 *   outgoing_message_max_recipients = -1,
 * )
 */
class LogGateway extends SmsGatewayPluginBase implements ContainerFactoryPluginInterface {

  public const PLUGIN_ID = 'log';

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a LogGateway object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    LoggerChannelFactoryInterface $logger_factory,
    protected TimeInterface $time,
  ) {
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
      $container->get('logger.factory'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function send(SmsMessageInterface $sms) {
    $this->logger->notice('SMS message sent to %number with the text: @message', [
      '%number' => implode(', ', $sms->getRecipients()),
      '@message' => $sms->getMessage(),
    ]);

    $result = new SmsMessageResult();
    foreach ($sms->getRecipients() as $number) {
      $report = (new SmsDeliveryReport())
        ->setRecipient($number)
        ->setStatus(SmsMessageReportStatus::DELIVERED)
        ->setStatusMessage('DELIVERED')
        ->setTimeDelivered($this->time->getRequestTime());
      $result->addReport($report);
    }

    return $result;
  }

}
