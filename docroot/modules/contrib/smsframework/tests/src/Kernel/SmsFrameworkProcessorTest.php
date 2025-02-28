<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Kernel;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\sms\Direction;
use Drupal\sms\Entity\SmsGatewayInterface;
use Drupal\sms\Entity\SmsMessage;
use Drupal\sms\Exception\SmsException;
use Drupal\sms\Exception\SmsPluginReportException;
use Drupal\sms\Message\SmsMessageResult;
use Drupal\sms\Provider\SmsProviderInterface;

/**
 * Tests functionality provided by the SMS message event subscriber.
 *
 * @group SMS Framework
 * @coversDefaultClass \Drupal\sms\EventSubscriber\SmsMessageProcessor
 */
final class SmsFrameworkProcessorTest extends SmsFrameworkKernelBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sms', 'sms_test', 'sms_test_gateway', 'field', 'telephone',
    'dynamic_entity_reference',
  ];

  /**
   * SMS message entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private EntityStorageInterface $smsStorage;

  /**
   * The SMS provider.
   *
   * @var \Drupal\sms\Provider\SmsProviderInterface
   */
  private SmsProviderInterface $smsProvider;

  /**
   * A memory gateway.
   *
   * @var \Drupal\sms\Entity\SmsGatewayInterface
   */
  private SmsGatewayInterface $gatewayMemory;

  /**
   * A memory gateway.
   *
   * @var \Drupal\sms\Entity\SmsGatewayInterface
   */
  private SmsGatewayInterface $gatewayOutgoingResult;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('sms');
    $this->installEntitySchema('sms_result');
    $this->installEntitySchema('sms_report');

    $this->gatewayMemory = $this->createMemoryGateway();
    $this->gatewayOutgoingResult = $this->createMemoryGateway(['plugin' => 'memory_outgoing_result']);
    $this->smsStorage = $this->container->get('entity_type.manager')
      ->getStorage('sms');
    $this->smsProvider = $this->container->get('sms.provider');
  }

  /**
   * Ensure exception thrown if incoming message is missing a result.
   *
   * @covers ::ensureReportsPreprocess
   */
  public function testIncomingNoResult(): void {
    $sms_message = SmsMessage::create()
      ->setDirection(Direction::INCOMING)
      ->setMessage($this->randomString())
      ->addRecipients($this->randomPhoneNumbers())
      ->setGateway($this->gatewayMemory);

    $this->expectException(SmsPluginReportException::class);
    $this->expectExceptionMessage('Missing result for message.');
    $this->smsProvider->queue($sms_message);
  }

  /**
   * Ensure exception thrown if incoming message is missing recipient reports.
   *
   * @covers ::ensureReportsPreprocess
   */
  public function testIncomingMissingReports(): void {
    $result = new SmsMessageResult();
    $sms_message = SmsMessage::create()
      ->setDirection(Direction::INCOMING)
      ->setMessage($this->randomString())
      ->addRecipients($this->randomPhoneNumbers())
      ->setGateway($this->gatewayMemory)
      ->setResult($result);

    $recipient_count = count($sms_message->getRecipients());
    $this->expectException(SmsPluginReportException::class);
    $this->expectExceptionMessage("Missing reports for $recipient_count recipient(s).");
    $this->smsProvider->queue($sms_message);
  }

  /**
   * Ensure exception thrown if outgoing message is missing recipient reports.
   *
   * @covers ::ensureReportsPreprocess
   */
  public function testOutgoingMissingReports(): void {
    $this->setFallbackGateway($this->gatewayOutgoingResult);

    $delete_count = rand(1, 5);
    \Drupal::state()->set('sms_test_gateway.memory_outgoing_result.delete_reports', $delete_count);

    // Must skip queue for send() for post-process to run.
    $this->gatewayOutgoingResult
      ->setSkipQueue(TRUE)
      ->save();

    $sms_message = SmsMessage::create()
      ->setDirection(Direction::OUTGOING)
      ->setMessage($this->randomString())
      ->addRecipients($this->randomPhoneNumbers($delete_count + 1));

    $this->expectException(SmsPluginReportException::class);
    $this->expectExceptionMessage("Missing reports for $delete_count recipient(s).");
    $this->smsProvider->queue($sms_message);
  }

  /**
   * Tests exception is thrown if gateway is not set on incoming messages.
   *
   * @covers ::ensureIncomingSupport
   */
  public function testIncomingMissingGateway(): void {
    $sms_message = SmsMessage::create()
      ->setDirection(Direction::INCOMING)
      ->setMessage($this->randomString())
      ->addRecipients($this->randomPhoneNumbers());

    $this->expectException(SmsException::class);
    $this->expectExceptionMessage('Gateway not set on incoming message');
    $this->smsProvider->queue($sms_message);
  }

  /**
   * Tests exception is thrown if gateway does not support incoming messages.
   *
   * @covers ::ensureIncomingSupport
   */
  public function testIncomingUnSupported(): void {
    $gateway = $this->createMemoryGateway([
      'plugin' => 'capabilities_default',
    ]);

    $sms_message = SmsMessage::create()
      ->setDirection(Direction::INCOMING)
      ->setMessage($this->randomString())
      ->addRecipients($this->randomPhoneNumbers())
      ->setGateway($gateway);

    $this->expectException(SmsException::class);
    $this->expectExceptionMessage("Gateway `" . $gateway->id() . "` does not support incoming messages.");
    $this->smsProvider->queue($sms_message);
  }

}
