<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Kernel;

use Drupal\Core\CronInterface;
use Drupal\sms\Entity\SmsGatewayInterface;
use Drupal\sms\Entity\SmsMessage;
use Drupal\sms\Direction;
use Drupal\sms\Entity\SmsMessageInterface;
use Drupal\sms\Plugin\QueueWorker\SmsProcessor;
use Drupal\sms\Provider\SmsProviderInterface;
use Drupal\sms\Provider\SmsQueueProcessorInterface;

/**
 * Tests behaviour of SMS Framework message queue.
 *
 * @group SMS Framework
 */
final class SmsFrameworkQueueTest extends SmsFrameworkKernelBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sms', 'sms_test_gateway', 'field', 'telephone', 'dynamic_entity_reference',
  ];

  /**
   * The SMS provider.
   *
   * @var \Drupal\sms\Provider\SmsProviderInterface
   */
  private SmsProviderInterface $smsProvider;

  /**
   * The SMS queue processor.
   *
   * @var \Drupal\sms\Provider\SmsQueueProcessorInterface
   */
  private SmsQueueProcessorInterface $smsQueueProcessor;

  /**
   * A memory gateway.
   *
   * @var \Drupal\sms\Entity\SmsGatewayInterface
   */
  private SmsGatewayInterface $gateway;

  /**
   * The cron service.
   *
   * @var \Drupal\Core\Cron
   */
  private CronInterface $cronService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('sms');
    $this->installEntitySchema('sms_result');
    $this->installEntitySchema('sms_report');

    $this->gateway = $this->createMemoryGateway();
    $this->smsProvider = $this->container->get('sms.provider');
    $this->setFallbackGateway($this->gateway);
    $this->smsQueueProcessor = $this->container->get('sms.queue');
    $this->cronService = $this->container->get('cron');
  }

  /**
   * Tests unqueued unprocessed messages are added to the Drupal queue system.
   */
  public function testProcessUnqueued(): void {
    $sms_message = $this->createSmsMessage();

    $result = $this->smsProvider->queue($sms_message);
    $id = $result[0]->id();

    // Call processUnqueued manually so cron does not send the message with
    // queue workers.
    $this->smsQueueProcessor->processUnqueued();
    $sms_message_saved = SmsMessage::load($id);

    static::assertTrue($sms_message_saved->isQueued(), 'SMS message is queued.');
    static::assertEquals(1, \Drupal::queue(SmsProcessor::PLUGIN_ID)->numberOfItems(), 'SMS message processor queue item created.');
  }

  /**
   * Test message is queued and received on cron run.
   */
  public function testQueueIncoming(): void {
    $sms_message = $this->createSmsMessage()
      ->setDirection(Direction::INCOMING)
      ->addRecipients($this->randomPhoneNumbers())
      ->setGateway($this->gateway);
    $sms_message->setResult($this->createMessageResult($sms_message));

    $this->smsProvider->queue($sms_message);
    static::assertCount(0, $this->getTestMessages($this->gateway), 'Message not received yet.');

    $this->cronService->run();
    static::assertEquals($sms_message->getMessage(), sms_test_gateway_get_incoming()['message'], 'Message was received.');
  }

  /**
   * Test message is queued and sent on cron run.
   */
  public function testQueueOutgoing(): void {
    $sms_message = $this->createSmsMessage()
      ->setDirection(Direction::OUTGOING);
    $this->smsProvider->queue($sms_message);
    static::assertCount(0, $this->getTestMessages($this->gateway), 'Message not sent yet.');

    $this->cronService->run();
    static::assertCount(1, $this->getTestMessages($this->gateway), 'Message was sent.');
  }

  /**
   * Test message is delayed.
   */
  public function testQueueDelayed(): void {
    $sms_message = $this->createSmsMessage()
      ->setSendTime(\Drupal::time()->getRequestTime() + 9999);

    $this->smsProvider->queue($sms_message);

    $this->cronService->run();
    static::assertCount(0, $this->getTestMessages($this->gateway), 'Message not sent yet.');
  }

  /**
   * Test message is not delayed for schedule aware gateways..
   */
  public function testQueueNotDelayedScheduleAware(): void {
    $gateway = $this->createMemoryGateway(['plugin' => 'memory_schedule_aware']);

    $sms_message = $this->createSmsMessage()
      ->setSendTime(\Drupal::time()->getRequestTime() + 9999)
      ->setGateway($gateway);

    $this->smsProvider->queue($sms_message);

    $this->cronService->run();
    static::assertCount(1, $this->getTestMessages($gateway), 'Message sent.');
  }

  /**
   * Test retention is set to delete messages immediately after transmission.
   *
   * Tests \Drupal\sms\Plugin\QueueWorker\SmsProcessor.
   */
  public function testRetentionImmediateDelete(): void {
    $this->gateway
      ->setRetentionDuration(Direction::OUTGOING, 0)
      ->save();

    $sms_message = $this->createSmsMessage();
    $this->smsProvider->queue($sms_message);

    $this->cronService->run();
    static::assertCount(1, $this->getTestMessages($this->gateway), 'One message was sent.');
    static::assertCount(0, SmsMessage::loadMultiple(), 'There are no SMS entities in storage.');
  }

  /**
   * Test retention is set to keep messages after transmission.
   *
   * Tests \Drupal\sms\Plugin\QueueWorker\SmsProcessor.
   */
  public function testRetentionPersist(): void {
    $this->gateway
      ->setRetentionDuration(Direction::OUTGOING, 9999)
      ->save();

    $sms_message = $this->createSmsMessage();
    $this->smsProvider->queue($sms_message);

    $this->cronService->run();
    $sms_messages = SmsMessage::loadMultiple();
    $sms_message_new = reset($sms_messages);

    static::assertCount(1, $this->getTestMessages($this->gateway), 'One message was sent.');
    static::assertCount(1, $sms_messages, 'There are SMS entities in storage.');
    static::assertEquals(\Drupal::time()->getRequestTime(), $sms_message_new->getProcessedTime());
    static::assertEquals(FALSE, $sms_message_new->isQueued());
  }

  /**
   * Test retention is set to keep messages forever.
   */
  public function testRetentionUnlimited(): void {
    $this->gateway
      ->setRetentionDuration(Direction::OUTGOING, -1)
      ->save();

    $this->createSmsMessage()
      ->setGateway($this->gateway)
      ->setQueued(FALSE)
      ->setProcessedTime(1)
      ->save();

    // Garbage collect.
    $this->cronService->run();

    static::assertCount(1, SmsMessage::loadMultiple(), 'There are SMS entities in storage.');
  }

  /**
   * Create a SMS message entity for testing.
   *
   * @param array $values
   *   An mixed array of values to pass when creating the SMS message entity.
   *
   * @return \Drupal\sms\Entity\SmsMessageInterface
   *   A SMS message entity for testing.
   */
  protected function createSmsMessage(array $values = []): SmsMessageInterface {
    return SmsMessage::create($values)
      ->setDirection(Direction::OUTGOING)
      ->setMessage($this->randomString())
      ->addRecipients($this->randomPhoneNumbers(1));
  }

}
