<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Kernel;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\sms\Entity\SmsGatewayInterface;
use Drupal\sms\Entity\SmsMessageInterface as SmsMessageEntityInterface;
use Drupal\sms\Exception\SmsDirectionException;
use Drupal\sms\Exception\SmsException;
use Drupal\sms\Exception\RecipientRouteException;
use Drupal\sms\Message\SmsMessage as StandardSmsMessage;
use Drupal\sms\Message\SmsMessageInterface as StandardSmsMessageInterface;
use Drupal\sms\Entity\SmsMessage;
use Drupal\sms\Entity\SmsMessageInterface;
use Drupal\sms\Entity\SmsGateway;
use Drupal\sms\Direction;
use Drupal\sms\Event\SmsEvents;
use Drupal\sms\Message\SmsMessageResultInterface;
use Drupal\sms\Provider\SmsProviderInterface;

/**
 * Tests SMS Framework provider service.
 *
 * @group SMS Framework
 * @coversDefaultClass \Drupal\sms\Provider\DefaultSmsProvider
 */
final class SmsFrameworkProviderTest extends SmsFrameworkKernelBase {

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
  private SmsGatewayInterface $gateway;

  /**
   * An incoming gateway.
   *
   * @var \Drupal\sms\Entity\SmsGatewayInterface
   */
  private SmsGatewayInterface $incomingGateway;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('sms');
    $this->installEntitySchema('sms_result');
    $this->installEntitySchema('sms_report');

    $this->gateway = $this->createMemoryGateway();
    $this->incomingGateway = $this->createMemoryGateway(['plugin' => 'incoming']);
    $this->smsStorage = $this->container->get('entity_type.manager')
      ->getStorage('sms');
    $this->smsProvider = $this->container->get('sms.provider');
    $this->setFallbackGateway($this->gateway);
  }

  /**
   * Test message is sent immediately.
   *
   * @covers ::send
   */
  public function testSend(): void {
    $message = $this->createSmsMessage()
      ->addRecipients($this->randomPhoneNumbers());

    $sms_messages = $this->smsProvider->send($message);

    static::assertCount(1, $sms_messages, 'Return value contains 1 item.');
    static::assertTrue($sms_messages[0] instanceof StandardSmsMessageInterface, 'Return value is a SMS message.');
    static::assertCount(1, $this->getTestMessages($this->gateway));
    static::assertTrue($sms_messages[0]->getResult() instanceof SmsMessageResultInterface);
  }

  /**
   * Ensures direction is set by the provider.
   *
   * @covers ::send
   */
  public function testSendNoDirection(): void {
    $sms_message = SmsMessage::create()
      ->setMessage($this->randomString())
      ->addRecipients($this->randomPhoneNumbers())
      ->setGateway($this->gateway);

    // This method will set direction.
    $this->smsProvider->send($sms_message);

    $messages = $this->getTestMessages($this->gateway);
    static::assertCount(1, ($messages), 'Message was added to outgoing queue without direction being explicitly set');
    static::assertEquals(Direction::OUTGOING, $messages[0]->getDirection(), 'Message direction set to outgoing.');
  }

  /**
   * Test message is received.
   *
   * @covers ::incoming
   */
  public function testIncoming(): void {
    $message = $this->randomString();
    $sms_message = SmsMessage::create()
      ->setDirection(Direction::INCOMING)
      ->setMessage($message)
      ->setGateway($this->gateway);
    $sms_message->setResult($this->createMessageResult($sms_message));

    $sms_messages = $this->smsProvider->incoming($sms_message);

    static::assertEquals($message, sms_test_gateway_get_incoming()['message'], 'Message was received.');
    static::assertCount(1, $sms_messages, 'Return value contains 1 item.');
    static::assertTrue($sms_messages[0] instanceof StandardSmsMessageInterface, 'Return value is a SMS message.');
    static::assertTrue($sms_messages[0]->getResult() instanceof SmsMessageResultInterface);
  }

  /**
   * Ensures direction is set by the provider.
   *
   * @covers ::incoming
   */
  public function testIncomingNoDirection(): void {
    $sms_message = SmsMessage::create()
      ->setMessage($this->randomString())
      ->addRecipients($this->randomPhoneNumbers())
      ->setGateway($this->incomingGateway);
    $sms_message->setResult($this->createMessageResult($sms_message));

    // This method will set direction.
    $this->smsProvider->incoming($sms_message);

    $messages = $this->getIncomingMessages($this->incomingGateway);
    static::assertCount(1, $messages, 'Message was added to incoming queue without direction being explicitly set');
    static::assertEquals(Direction::INCOMING, $messages[0]->getDirection(), 'Message direction set to incoming.');
  }

  /**
   * Ensures incoming message without recipients do not trigger exception.
   */
  public function testIncomingNoRecipients(): void {
    $this->incomingGateway
      ->setSkipQueue(TRUE)
      ->save();

    $sms_message = SmsMessage::create()
      ->setMessage($this->randomString())
      ->setGateway($this->incomingGateway)
      ->setDirection(Direction::INCOMING);
    $sms_message->setResult($this->createMessageResult($sms_message));

    $this->smsProvider->queue($sms_message);

    $messages = $this->getIncomingMessages($this->incomingGateway);
    static::assertCount(1, $messages, 'Message was added to incoming queue without recipients.');
  }

  /**
   * Ensure no messages sent if no recipients.
   */
  public function testNoSendNoRecipients(): void {
    $sms_message = SmsMessage::create()
      ->setDirection(Direction::OUTGOING)
      ->setMessage($this->randomString());
    $this->expectException(RecipientRouteException::class);
    $this->expectExceptionMessage('There are no recipients');
    $this->smsProvider->send($sms_message);
    static::assertCount(0, $this->getTestMessages($this->gateway));
  }

  /**
   * Ensures validation failure if no message.
   */
  public function testNoSendNoMessage(): void {
    $sms_message = SmsMessage::create()
      ->setDirection(Direction::OUTGOING)
      ->addRecipients($this->randomPhoneNumbers());
    $this->expectException(SmsException::class);
    $this->expectExceptionMessage('Can not queue SMS message because there are 1 validation error(s): [message]: This value should not be null.');
    $this->smsProvider->queue($sms_message);
  }

  /**
   * Ensures exception if missing direction for queue method.
   *
   * @covers ::queue
   */
  public function testQueueNoDirection(): void {
    $sms_message = SmsMessage::create()
      ->setMessage($this->randomString())
      ->addRecipients($this->randomPhoneNumbers());
    $this->expectException(SmsDirectionException::class);
    $this->expectExceptionMessage('Missing direction for message.');
    $this->smsProvider->queue($sms_message);
  }

  /**
   * Test message is not sent because no gateway is set.
   *
   * @covers ::send
   */
  public function testSendNoFallbackGateway(): void {
    $this->setFallbackGateway(NULL);
    $this->expectException(RecipientRouteException::class);
    $message = $this->createSmsMessage()
      ->addRecipients($this->randomPhoneNumbers());
    $this->smsProvider->send($message);
  }

  /**
   * Test message is saved.
   */
  public function testQueueBasic(): void {
    $sms_message = $this->createSmsMessage()
      ->addRecipients($this->randomPhoneNumbers());
    $return = $this->smsProvider->queue($sms_message);
    static::assertCount(1, SmsMessage::loadMultiple(), 'SMS message saved.');
    static::assertCount(1, $return);
    static::assertTrue($return[0] instanceof SmsMessageInterface);
  }

  /**
   * Test message is not queued because no gateway is set.
   *
   * @covers ::send
   */
  public function testQueueNoFallbackGateway(): void {
    $this->setFallbackGateway(NULL);
    $this->expectException(RecipientRouteException::class);
    $message = $this->createSmsMessage()
      ->addRecipients($this->randomPhoneNumbers());
    $this->smsProvider->queue($message);
  }

  /**
   * Test message is sent immediately.
   */
  public function testSkipQueue(): void {
    $this->gateway
      ->setSkipQueue(TRUE)
      ->save();
    $sms_message = $this->createSmsMessage()
      ->addRecipients($this->randomPhoneNumbers());
    $this->smsProvider->queue($sms_message);
    static::assertCount(1, $this->getTestMessages($this->gateway));
  }

  /**
   * Test sending standard SMS object queue in.
   */
  public function testQueueIn(): void {
    $sms_message = new StandardSmsMessage();
    $sms_message
      ->addRecipients($this->randomPhoneNumbers())
      ->setMessage($this->randomString())
      ->setDirection(Direction::INCOMING)
      ->setGateway($this->gateway);
    $sms_message->setResult($this->createMessageResult($sms_message));

    $sms_messages = $this->smsStorage
      ->loadByProperties(['direction' => Direction::INCOMING]);
    static::assertCount(0, $sms_messages, 'There is zero SMS message in the incoming queue.');

    $this->smsProvider
      ->queue($sms_message);

    $sms_messages = $this->smsStorage
      ->loadByProperties(['direction' => Direction::INCOMING]);
    static::assertCount(1, $sms_messages, 'There is one SMS message in the incoming queue.');

    $sms_message_loaded = reset($sms_messages);
    static::assertEquals(Direction::INCOMING, $sms_message_loaded->getDirection());
  }

  /**
   * Test sending standard SMS object queue out.
   */
  public function testQueueOut(): void {
    $sms_message = new StandardSmsMessage();
    $sms_message
      ->addRecipients($this->randomPhoneNumbers())
      ->setMessage($this->randomString())
      ->setDirection(Direction::OUTGOING);

    $sms_messages = $this->smsStorage
      ->loadByProperties(['direction' => Direction::OUTGOING]);
    static::assertCount(0, $sms_messages, 'There is zero SMS message in the outgoing queue.');

    $this->smsProvider->queue($sms_message);

    $sms_messages = $this->smsStorage
      ->loadByProperties(['direction' => Direction::OUTGOING]);
    static::assertCount(1, $sms_messages, 'There is one SMS message in the outgoing queue.');

    $sms_message_loaded = reset($sms_messages);
    static::assertEquals(Direction::OUTGOING, $sms_message_loaded->getDirection());
  }

  /**
   * Test sending standard SMS object queue out skips queue.
   */
  public function testQueueOutSkipQueue(): void {
    $this->gateway
      ->setSkipQueue(TRUE)
      ->save();

    $sms_message = new StandardSmsMessage('', [], '', [], NULL);
    $sms_message
      ->addRecipients($this->randomPhoneNumbers())
      ->setMessage($this->randomString())
      ->setDirection(Direction::OUTGOING);

    $this->smsProvider->queue($sms_message);
    static::assertCount(1, $this->getTestMessages($this->gateway), 'One standard SMS send skipped queue.');
  }

  /**
   * Test an exception is thrown if a message has no recipients.
   */
  public function testNoRecipients(): void {
    $this->expectException(RecipientRouteException::class);
    $this->expectExceptionMessage('There are no recipients.');
    $sms_message = SmsMessage::create()
      ->setDirection(Direction::OUTGOING)
      ->setMessage($this->randomString());
    $this->smsProvider->send($sms_message);
  }

  /**
   * Test message is split into multiple messages if gateway demands it.
   */
  public function testChunking(): void {
    $gateway_chunked = SmsGateway::create([
      'plugin' => 'memory_chunked',
      'id' => 'memory_chunked',
      'settings' => ['gateway_id' => 'memory_chunked'],
    ]);
    $gateway_chunked->enable()->save();

    $sms_message = $this->createSmsMessage()
      ->setGateway($gateway_chunked)
      ->addRecipients(['123123123', '456456456', '789789789']);
    $return = $this->smsProvider->queue($sms_message);

    static::assertCount(2, SmsMessage::loadMultiple(), 'One SMS message has been split into two.');
    static::assertCount(2, $return, 'Provider queue method returned two messages.');
  }

  /**
   * Test message is not into multiple messages if gateway defines no chunking.
   */
  public function testNoChunking(): void {
    $sms_message = $this->createSmsMessage()
      ->setGateway($this->gateway)
      ->addRecipients($this->randomPhoneNumbers());

    $this->smsProvider->queue($sms_message);

    static::assertCount(1, SmsMessage::loadMultiple(), 'SMS message has not been split.');
  }

  /**
   * Test incoming messages do not get chunked.
   */
  public function testIncomingNotChunked(): void {
    $this->incomingGateway
      ->setSkipQueue(TRUE)
      ->save();

    $message = (new StandardSmsMessage())
      ->setMessage($this->randomString())
      ->addRecipients($this->randomPhoneNumbers())
      ->setDirection(Direction::INCOMING)
      ->setGateway($this->incomingGateway);
    $message->setResult($this->createMessageResult($message));

    $this->smsProvider->queue($message);

    $incoming_messages = $this->getIncomingMessages($this->incomingGateway);
    static::assertCount(1, $incoming_messages, 'There is one incoming message.');
  }

  /**
   * Ensure events are executed when a message added to the outgoing queue.
   */
  public function testEventsQueueOutgoing(): void {
    $sms_message = $this->createSmsMessage()
      ->setGateway($this->gateway)
      ->setDirection(Direction::OUTGOING)
      ->addRecipients($this->randomPhoneNumbers());

    $this->smsProvider->queue($sms_message);

    $expected = [
      SmsEvents::MESSAGE_PRE_PROCESS,
      SmsEvents::MESSAGE_QUEUE_PRE_PROCESS,
      SmsEvents::MESSAGE_QUEUE_POST_PROCESS,
    ];
    $execution_order = \Drupal::state()->get('sms_test_event_subscriber__execution_order', []);
    static::assertEquals($expected, $execution_order);

    // Ensure SmsEvents::MESSAGE_PRE_PROCESS is not executed. See
    // '_skip_preprocess_event' option.
    $this->container->get('cron')->run();

    $expected[] = SmsEvents::MESSAGE_OUTGOING_PRE_PROCESS;
    $expected[] = SmsEvents::MESSAGE_OUTGOING_POST_PROCESS;
    $expected[] = SmsEvents::MESSAGE_POST_PROCESS;

    $execution_order = \Drupal::state()->get('sms_test_event_subscriber__execution_order', []);
    static::assertEquals($expected, $execution_order);
  }

  /**
   * Test events for outgoing queue skip queue.
   *
   * Ensure events are executed when a message added to the outgoing queue and
   * the gateway is set to skip queue.
   */
  public function testEventsQueueOutgoingSkipQueue(): void {
    $this->gateway
      ->setSkipQueue(TRUE)
      ->save();

    $sms_message = $this->createSmsMessage()
      ->setGateway($this->gateway)
      ->setDirection(Direction::OUTGOING)
      ->addRecipients($this->randomPhoneNumbers());

    $this->smsProvider->queue($sms_message);

    $expected = [
      SmsEvents::MESSAGE_PRE_PROCESS,
      SmsEvents::MESSAGE_QUEUE_PRE_PROCESS,
      SmsEvents::MESSAGE_OUTGOING_PRE_PROCESS,
      SmsEvents::MESSAGE_OUTGOING_POST_PROCESS,
      SmsEvents::MESSAGE_POST_PROCESS,
      SmsEvents::MESSAGE_QUEUE_POST_PROCESS,
    ];
    $execution_order = \Drupal::state()->get('sms_test_event_subscriber__execution_order', []);
    static::assertEquals($expected, $execution_order);
  }

  /**
   * Ensure events are executed when a message added to the incoming queue.
   */
  public function testEventsQueueIncoming(): void {
    $sms_message = $this->createSmsMessage()
      ->setGateway($this->gateway)
      ->setDirection(Direction::INCOMING)
      ->addRecipients($this->randomPhoneNumbers());
    $sms_message->setResult($this->createMessageResult($sms_message));

    $this->smsProvider->queue($sms_message);

    $expected = [
      SmsEvents::MESSAGE_PRE_PROCESS,
      SmsEvents::MESSAGE_QUEUE_PRE_PROCESS,
      SmsEvents::MESSAGE_QUEUE_POST_PROCESS,
    ];
    $execution_order = \Drupal::state()->get('sms_test_event_subscriber__execution_order', []);
    static::assertEquals($expected, $execution_order);

    // Ensure SmsEvents::MESSAGE_PRE_PROCESS is not executed. See
    // '_skip_preprocess_event' option.
    $this->container->get('cron')->run();

    $expected[] = SmsEvents::MESSAGE_INCOMING_PRE_PROCESS;
    $expected[] = 'Drupal\sms_test_gateway\Plugin\SmsGateway\Memory::incomingEvent';
    $expected[] = SmsEvents::MESSAGE_INCOMING_POST_PROCESS;
    $expected[] = SmsEvents::MESSAGE_POST_PROCESS;

    $execution_order = \Drupal::state()->get('sms_test_event_subscriber__execution_order', []);
    static::assertEquals($expected, $execution_order);
  }

  /**
   * Tests events for incoming queue skip queue.
   *
   * Ensure events are executed when a message added to the incoming queue and
   * the gateway is set to skip queue.
   */
  public function testEventsQueueIncomingSkipQueue(): void {
    $this->gateway
      ->setSkipQueue(TRUE)
      ->save();

    $sms_message = $this->createSmsMessage()
      ->setGateway($this->gateway)
      ->setDirection(Direction::INCOMING)
      ->addRecipients($this->randomPhoneNumbers());
    $sms_message->setResult($this->createMessageResult($sms_message));

    $this->smsProvider->queue($sms_message);

    $expected = [
      SmsEvents::MESSAGE_PRE_PROCESS,
      SmsEvents::MESSAGE_QUEUE_PRE_PROCESS,
      SmsEvents::MESSAGE_INCOMING_PRE_PROCESS,
      'Drupal\sms_test_gateway\Plugin\SmsGateway\Memory::incomingEvent',
      SmsEvents::MESSAGE_INCOMING_POST_PROCESS,
      SmsEvents::MESSAGE_POST_PROCESS,
      SmsEvents::MESSAGE_QUEUE_POST_PROCESS,
    ];
    $execution_order = \Drupal::state()->get('sms_test_event_subscriber__execution_order', []);
    static::assertEquals($expected, $execution_order);
  }

  /**
   * Ensure events are executed when a message is sent.
   */
  public function testEventsOutgoing(): void {
    $sms_message = $this->createSmsMessage()
      ->setGateway($this->gateway)
      ->setDirection(Direction::OUTGOING)
      ->addRecipients($this->randomPhoneNumbers());

    $this->smsProvider->send($sms_message);

    $expected = [
      SmsEvents::MESSAGE_PRE_PROCESS,
      SmsEvents::MESSAGE_OUTGOING_PRE_PROCESS,
      SmsEvents::MESSAGE_OUTGOING_POST_PROCESS,
      SmsEvents::MESSAGE_POST_PROCESS,
    ];
    $execution_order = \Drupal::state()->get('sms_test_event_subscriber__execution_order', []);
    static::assertEquals($expected, $execution_order);
  }

  /**
   * Ensure events are executed when a message is received.
   */
  public function testEventsIncoming(): void {
    $sms_message = $this->createSmsMessage()
      ->setGateway($this->gateway)
      ->setDirection(Direction::INCOMING)
      ->addRecipients($this->randomPhoneNumbers());
    $sms_message->setResult($this->createMessageResult($sms_message));

    $this->smsProvider->incoming($sms_message);

    $expected = [
      SmsEvents::MESSAGE_PRE_PROCESS,
      SmsEvents::MESSAGE_INCOMING_PRE_PROCESS,
      'Drupal\sms_test_gateway\Plugin\SmsGateway\Memory::incomingEvent',
      SmsEvents::MESSAGE_INCOMING_POST_PROCESS,
      SmsEvents::MESSAGE_POST_PROCESS,
    ];
    $execution_order = \Drupal::state()->get('sms_test_event_subscriber__execution_order', []);
    static::assertEquals($expected, $execution_order);
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
  protected function createSmsMessage(array $values = []): SmsMessageEntityInterface {
    return SmsMessage::create($values)
      ->setDirection(Direction::OUTGOING)
      ->setMessage($this->randomString());
  }

}
