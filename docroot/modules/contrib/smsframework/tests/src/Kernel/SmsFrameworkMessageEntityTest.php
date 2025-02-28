<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\sms\Direction;
use Drupal\sms\Entity\SmsDeliveryReport;
use Drupal\sms\Entity\SmsMessageInterface;
use Drupal\sms\Entity\SmsMessageResult;
use Drupal\sms\Message\SmsMessage as StandardSmsMessage;
use Drupal\sms\Entity\SmsMessage;
use Drupal\sms\Message\SmsMessageResultInterface;
use Drupal\Tests\sms\Functional\SmsFrameworkMessageTestTrait;
use Drupal\Tests\sms\Functional\SmsFrameworkTestTrait;
use Drupal\user\Entity\User;

/**
 * Tests SMS message entity.
 *
 * @group SMS Framework
 * @coversDefaultClass \Drupal\sms\Entity\SmsMessage
 */
final class SmsFrameworkMessageEntityTest extends SmsFrameworkKernelBase {

  use SmsFrameworkTestTrait;
  use SmsFrameworkMessageTestTrait {
    // Remove 'test' prefix so it will not be run by test runner, rename so we
    // can override.
    testUid as originalUid;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sms', 'sms_test_gateway', 'telephone', 'dynamic_entity_reference', 'user',
    'sms_test_time',
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('sms');
    $this->installEntitySchema('sms_result');
    $this->installEntitySchema('sms_report');
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
  }

  /**
   * Create a SMS message object for testing.
   *
   * @param array $values
   *   An mixed array of values to pass when creating the SMS message entity.
   *
   * @return \Drupal\sms\Entity\SmsMessageInterface
   *   A SMS message object for testing.
   */
  protected function createSmsMessage(array $values = []): SmsMessageInterface {
    return SmsMessage::create($values);
  }

  /**
   * Tests validation violation when message is empty.
   */
  public function testMessageEmpty(): void {
    $sms_message = $this->createSmsMessage();
    $this->assertTrue(in_array('message', $sms_message->validate()->getFieldNames()));
  }

  /**
   * {@inheritdoc}
   */
  public function testUid(): void {
    // User must exist or setUid will throw an exception.
    User::create(['uid' => 22, 'name' => 'user'])
      ->save();
    $this->originalUid();
  }

  /**
   * Test sender name is correct when sender name or sender entity is set.
   */
  public function testSenderNameWithSenderEntity(): void {
    $sender_name = $this->randomMachineName();
    $sender = EntityTest::create()
      ->setName($this->randomMachineName());
    $sender->save();

    $sms_message1 = $this->createSmsMessage();
    $sms_message1->setSender($sender_name);
    $sms_message1->setSenderEntity($sender);
    $this->assertEquals($sender_name, $sms_message1->getSender());

    $sms_message2 = $this->createSmsMessage();
    $sms_message2->setSenderEntity($sender);
    $this->assertEquals($sender->label(), $sms_message2->getSender());
  }

  /**
   * Tests entity validation for direction property of SMS message entity.
   *
   * @covers ::getDirection
   * @covers ::setDirection
   */
  public function testDirectionEntityValidation(): void {
    // Check for validation violation for missing direction.
    $sms_message1 = $this->createSmsMessage();
    $this->assertTrue(in_array('direction', $sms_message1->validate()->getFieldNames()));
  }

  /**
   * Tests gateway plugin of SMS messages.
   *
   * @covers ::getGateway
   * @covers ::setGateway
   */
  public function testGateway(): void {
    // Check for validation violation for missing gateway.
    $sms_message1 = $this->createSmsMessage();
    $this->assertTrue(in_array('gateway', $sms_message1->validate()->getFieldNames()));

    $gateway = $this->createMemoryGateway();
    $sms_message2 = $this->createSmsMessage();
    $sms_message2->setGateway($gateway);
    $this->assertEquals($gateway, $sms_message2->getGateway());
  }

  /**
   * Tests sender entity.
   *
   * @covers ::getSenderEntity
   * @covers ::setSenderEntity
   */
  public function testSenderEntity(): void {
    $sms_message1 = $this->createSmsMessage();
    $this->assertEquals(NULL, $sms_message1->getSenderEntity());

    $sender = EntityTest::create();
    $sender->save();
    $sms_message2 = $this->createSmsMessage();
    $sms_message2->setSenderEntity($sender);
    $this->assertEquals($sender->id(), $sms_message2->getSenderEntity()->id());
  }

  /**
   * Tests recipient entity.
   *
   * @covers ::getRecipientEntity
   * @covers ::setRecipientEntity
   */
  public function testRecipientEntity(): void {
    $sms_message1 = $this->createSmsMessage();
    $this->assertEquals(NULL, $sms_message1->getRecipientEntity());

    $sender = EntityTest::create();
    $sender->save();
    $sms_message2 = $this->createSmsMessage();
    $sms_message2->setRecipientEntity($sender);
    $this->assertEquals($sender->id(), $sms_message2->getRecipientEntity()->id());
  }

  /**
   * Tests is queued.
   *
   * @covers ::isQueued
   * @covers ::setQueued
   */
  public function testQueued(): void {
    $sms_message1 = $this->createSmsMessage();
    $this->assertFalse($sms_message1->isQueued());

    $sms_message2 = $this->createSmsMessage();
    $sms_message2->setQueued(TRUE);
    $this->assertTrue($sms_message2->isQueued());
  }

  /**
   * Tests created time.
   *
   * @covers ::getCreatedTime
   */
  public function testCreatedTime(): void {
    $sms_message = $this->createSmsMessage();
    // This test is currently marked as risky because CreatedItem still relies
    // on the REQUEST_TIME constant value, which used to be safe to rely on
    // within tests. Skipped may be removed after
    // https://www.drupal.org/project/drupal/issues/2903549 is resolved.
    $this->markTestSkipped();
    $this->assertEquals('877098600', $sms_message->getCreatedTime());
  }

  /**
   * Tests queue send time.
   *
   * @covers ::getSendTime
   * @covers ::setSendTime
   */
  public function testSendTime(): void {
    $sms_message1 = $this->createSmsMessage();
    // This test is currently marked as risky because CreatedItem still relies
    // on the REQUEST_TIME constant value, which used to be safe to rely on
    // within tests. Skipped may be removed after
    // https://www.drupal.org/project/drupal/issues/2903549 is resolved.
    $this->markTestSkipped();
    $this->assertEquals('877098600', $sms_message1->getSendTime());
    $time = (new \DateTime('+7 days'))->getTimestamp();
    $sms_message2 = $this->createSmsMessage();
    $sms_message2->setSendTime($time);
    $this->assertEquals($time, $sms_message2->getSendTime());
  }

  /**
   * Tests processed time.
   *
   * @covers ::getProcessedTime
   * @covers ::setProcessedTime
   */
  public function testProcessedTime(): void {
    $sms_message1 = $this->createSmsMessage();
    $this->assertEquals(NULL, $sms_message1->getProcessedTime());

    $time = (new DrupalDateTime('+7 days'))->format('U');
    $sms_message2 = $this->createSmsMessage();
    $sms_message2->setProcessedTime($time);
    $this->assertEquals($time, $sms_message2->getProcessedTime());
  }

  /**
   * Tests chunked SMS messages are unsaved entities.
   *
   * @covers ::chunkByRecipients
   */
  public function testChunkByRecipientsEntity(): void {
    $sms_message = $this->createSmsMessage();
    $sms_message->addRecipients(['100', '200']);
    $sms_messages = $sms_message->chunkByRecipients(1);
    $this->assertTrue($sms_messages[0]->isNew());
    $this->assertTrue($sms_messages[1]->isNew());
  }

  /**
   * Ensure data from standard SMS message are passed to SMS message entity.
   */
  public function testConvertToEntityFromStandardSmsMessage(): void {
    // Need ID otherwise we have to install system module and 'sequences' table.
    $user = User::create(['uid' => 1, 'name' => 'user']);
    $user->save();

    $gateway = $this->createMemoryGateway();
    $sender_number = $this->randomPhoneNumbers(1);
    $original = new StandardSmsMessage('', [], '', [], NULL);
    $original
      ->setAutomated(TRUE)
      ->setSender($this->randomMachineName())
      ->setSenderNumber($sender_number[0])
      ->addRecipients(['123123123', '456456456'])
      ->setMessage($this->randomMachineName())
      ->setUid($user->id())
      ->setGateway($gateway)
      ->setOption('foo', $this->randomMachineName())
      ->setOption('bar', $this->randomMachineName())
      ->setResult($this->createMessageResult($original));

    $sms_message = SmsMessage::convertFromSmsMessage($original);

    $this->assertEquals($original->isAutomated(), $sms_message->isAutomated());
    $this->assertEquals($original->getSender(), $sms_message->getSender());
    $this->assertEquals($original->getSenderNumber(), $sms_message->getSenderNumber());
    $this->assertEquals($original->getRecipients(), $sms_message->getRecipients());
    $this->assertEquals($original->getMessage(), $sms_message->getMessage());
    $this->assertEquals($user->id(), $sms_message->getSenderEntity()->id());
    $this->assertEquals($original->getOption('foo'), $sms_message->getOption('foo'));
    $this->assertEquals($original->getOption('bar'), $sms_message->getOption('bar'));
    $this->assertEquals($original->getGateway(), $sms_message->getGateway());
    $this->assertEquals($original->getResult()->getErrorMessage(), $sms_message->getResult()->getErrorMessage());
    $this->assertCount(count($original->getReports()), $sms_message->getReports());
    $this->assertEquals($original->getReport('123123123')->getRecipient(),
      $sms_message->getReport('123123123')->getRecipient());
    $this->assertEquals($original->getReport('456456456')->getRecipient(),
      $sms_message->getReport('456456456')->getRecipient());
  }

  /**
   * Ensure there is no data loss if an entity is passed to the converter.
   */
  public function testConvertToEntityFromEntitySmsMessage(): void {
    $recipient = EntityTest::create()
      ->setName($this->randomMachineName());
    $recipient->save();

    $original = SmsMessage::create();
    $original->setMessage($this->randomMachineName());
    // Use a method not common with standard SMS message class.
    $original->setRecipientEntity($recipient);

    $sms_message = SmsMessage::convertFromSmsMessage($original);
    $this->assertEquals($original->getMessage(), $sms_message->getMessage());
    $this->assertEquals($original->getRecipientEntity(), $sms_message->getRecipientEntity());
  }

  /**
   * Tests saving and retrieval of complete entity.
   */
  public function testSaveAndRetrieveSmsMessage(): void {
    /** @var \Drupal\sms\Entity\SmsMessageInterface $sms_message */
    $sms_message = SmsMessage::create()
      ->setMessage($this->randomMachineName(100))
      ->setSender($this->randomMachineName())
      ->setDirection(Direction::OUTGOING)
      ->addRecipients(['1234567890', '2345678901']);
    $sms_message
      ->setResult($this->createMessageResult($sms_message))
      ->save();
    $saved = SmsMessage::load($sms_message->id());
    $this->assertEquals($sms_message->getMessage(), $saved->getMessage());
    $this->assertEquals($sms_message->getSender(), $saved->getSender());
    $this->assertEquals($sms_message->getDirection(), $saved->getDirection());
    $this->assertEquals($sms_message->getRecipients(), $saved->getRecipients());
    $this->assertEquals($sms_message->getResult()->getErrorMessage(), $saved->getResult()->getErrorMessage());
    $this->assertCount(count($sms_message->getReports()), $saved->getReports());
    $this->assertCount(2, $sms_message->getReports());
  }

  /**
   * Tests that getResult returns null if no result is set.
   *
   * @covers ::getResult
   */
  public function testGetResultNoResult(): void {
    $sms_message = SmsMessage::create();
    $this->assertNull($sms_message->getResult());
  }

  /**
   * Tests cascade delete on the SMS message, result and reports.
   */
  public function testCascadeDelete(): void {
    /** @var \Drupal\sms\Entity\SmsMessageInterface $sms_message */
    $sms_message = SmsMessage::create()
      ->setMessage($this->getRandomGenerator()->paragraphs())
      ->setGateway($this->createMemoryGateway())
      ->addRecipients($this->randomPhoneNumbers())
      ->setSender($this->randomMachineName());

    $this->assertNull($sms_message->getResult());
    $sms_result = $this->createMessageResult($sms_message);
    $sms_message
      ->setResult($sms_result)
      ->save();
    $sms_reports = $sms_result->getReports();

    $this->assertInstanceOf(SmsMessageResultInterface::class, $sms_message->getResult());
    $this->assertInstanceOf(SmsMessageInterface::class, SmsMessage::load($sms_message->id()));
    $this->assertCount(count($sms_reports), SmsDeliveryReport::loadMultiple());

    // Delete the message and confirm that all has been removed.
    $sms_message->delete();

    $this->assertNull(SmsMessage::load($sms_message->id()));
    $this->assertEquals([], SmsMessageResult::loadMultiple());
    $this->assertEquals([], SmsDeliveryReport::loadMultiple());
  }

}
