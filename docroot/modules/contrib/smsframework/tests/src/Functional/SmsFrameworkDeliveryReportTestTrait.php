<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Functional;

use Drupal\sms\Message\SmsDeliveryReportInterface;

/**
 * Provides common tests for SmsDeliveryReport object and entity classes.
 */
trait SmsFrameworkDeliveryReportTestTrait {

  /**
   * Tests message ID.
   *
   * @covers ::getMessageId
   * @covers ::setMessageId
   */
  public function testMessageId(): void {
    $report = $this->createDeliveryReport();
    static::assertEquals('', $report->getMessageId(), 'Default value is empty string');

    $message_id = $this->getRandomGenerator()->string();
    $return = $report->setMessageId($message_id);

    static::assertTrue($return instanceof SmsDeliveryReportInterface);
    static::assertEquals($message_id, $report->getMessageId());
  }

  /**
   * Tests recipient.
   *
   * @covers ::getRecipient
   * @covers ::setRecipient
   */
  public function testRecipient(): void {
    $report = $this->createDeliveryReport();
    static::assertEquals('', $report->getRecipient(), 'Default value is empty string');

    $recipient = $this->getRandomGenerator()->string();
    $return = $report->setRecipient($recipient);

    static::assertTrue($return instanceof SmsDeliveryReportInterface);
    static::assertEquals($recipient, $report->getRecipient());
  }

  /**
   * Tests status.
   *
   * @covers ::getStatus
   * @covers ::setStatus
   */
  public function testStatus(): void {
    $report = $this->createDeliveryReport();
    static::assertNull($report->getStatus(), 'Default value is NULL');

    $status = $this->getRandomGenerator()->string();
    $return = $report->setStatus($status);

    static::assertTrue($return instanceof SmsDeliveryReportInterface);
    static::assertEquals($status, $report->getStatus());
  }

  /**
   * Tests status message.
   *
   * @covers ::getStatusMessage
   * @covers ::setStatusMessage
   */
  public function testStatusMessage(): void {
    $report = $this->createDeliveryReport();
    static::assertEquals('', $report->getStatusMessage(), 'Default value is empty string');

    $status_message = $this->getRandomGenerator()->string();
    $return = $report->setStatusMessage($status_message);

    static::assertTrue($return instanceof SmsDeliveryReportInterface);
    static::assertEquals($status_message, $report->getStatusMessage());
  }

  /**
   * Tests status time.
   *
   * @covers ::getStatusTime
   * @covers ::setStatusTime
   */
  public function testStatusTime(): void {
    $report = $this->createDeliveryReport();
    static::assertNull($report->getStatusTime(), 'Default value is NULL');

    $time = 123123123;
    $return = $report->setStatusTime($time);

    static::assertTrue($return instanceof SmsDeliveryReportInterface);
    static::assertEquals($time, $report->getStatusTime());
  }

  /**
   * Tests time queued.
   *
   * @covers ::getTimeQueued
   * @covers ::setTimeQueued
   */
  public function testTimeQueued(): void {
    $report = $this->createDeliveryReport();
    static::assertNull($report->getTimeQueued(), 'Default value is NULL');

    $time = 123123123;
    $return = $report->setTimeQueued($time);

    static::assertTrue($return instanceof SmsDeliveryReportInterface);
    static::assertEquals($time, $report->getTimeQueued());
  }

  /**
   * Tests time delivered.
   *
   * @covers ::getTimeDelivered
   * @covers ::setTimeDelivered
   */
  public function testTimeDelivered(): void {
    $report = $this->createDeliveryReport();
    static::assertNull($report->getTimeDelivered(), 'Default value is NULL');

    $time = 123123123;
    $return = $report->setTimeDelivered($time);

    static::assertTrue($return instanceof SmsDeliveryReportInterface);
    static::assertEquals($time, $report->getTimeDelivered());
  }

  /**
   * Creates an SMS delivery report for testing.
   *
   * @return \Drupal\sms\Message\SmsDeliveryReportInterface
   *   A delivery report for testing.
   */
  abstract protected function createDeliveryReport(): SmsDeliveryReportInterface;

}
