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
    $this->assertEquals('', $report->getMessageId(), 'Default value is empty string');

    $message_id = $this->getRandomGenerator()->string();
    $return = $report->setMessageId($message_id);

    $this->assertTrue($return instanceof SmsDeliveryReportInterface);
    $this->assertEquals($message_id, $report->getMessageId());
  }

  /**
   * Tests recipient.
   *
   * @covers ::getRecipient
   * @covers ::setRecipient
   */
  public function testRecipient(): void {
    $report = $this->createDeliveryReport();
    $this->assertEquals('', $report->getRecipient(), 'Default value is empty string');

    $recipient = $this->getRandomGenerator()->string();
    $return = $report->setRecipient($recipient);

    $this->assertTrue($return instanceof SmsDeliveryReportInterface);
    $this->assertEquals($recipient, $report->getRecipient());
  }

  /**
   * Tests status.
   *
   * @covers ::getStatus
   * @covers ::setStatus
   */
  public function testStatus(): void {
    $report = $this->createDeliveryReport();
    $this->assertNull($report->getStatus(), 'Default value is NULL');

    $status = $this->getRandomGenerator()->string();
    $return = $report->setStatus($status);

    $this->assertTrue($return instanceof SmsDeliveryReportInterface);
    $this->assertEquals($status, $report->getStatus());
  }

  /**
   * Tests status message.
   *
   * @covers ::getStatusMessage
   * @covers ::setStatusMessage
   */
  public function testStatusMessage(): void {
    $report = $this->createDeliveryReport();
    $this->assertEquals('', $report->getStatusMessage(), 'Default value is empty string');

    $status_message = $this->getRandomGenerator()->string();
    $return = $report->setStatusMessage($status_message);

    $this->assertTrue($return instanceof SmsDeliveryReportInterface);
    $this->assertEquals($status_message, $report->getStatusMessage());
  }

  /**
   * Tests status time.
   *
   * @covers ::getStatusTime
   * @covers ::setStatusTime
   */
  public function testStatusTime(): void {
    $report = $this->createDeliveryReport();
    $this->assertNull($report->getStatusTime(), 'Default value is NULL');

    $time = 123123123;
    $return = $report->setStatusTime($time);

    $this->assertTrue($return instanceof SmsDeliveryReportInterface);
    $this->assertEquals($time, $report->getStatusTime());
  }

  /**
   * Tests time queued.
   *
   * @covers ::getTimeQueued
   * @covers ::setTimeQueued
   */
  public function testTimeQueued(): void {
    $report = $this->createDeliveryReport();
    $this->assertNull($report->getTimeQueued(), 'Default value is NULL');

    $time = 123123123;
    $return = $report->setTimeQueued($time);

    $this->assertTrue($return instanceof SmsDeliveryReportInterface);
    $this->assertEquals($time, $report->getTimeQueued());
  }

  /**
   * Tests time delivered.
   *
   * @covers ::getTimeDelivered
   * @covers ::setTimeDelivered
   */
  public function testTimeDelivered(): void {
    $report = $this->createDeliveryReport();
    $this->assertNull($report->getTimeDelivered(), 'Default value is NULL');

    $time = 123123123;
    $return = $report->setTimeDelivered($time);

    $this->assertTrue($return instanceof SmsDeliveryReportInterface);
    $this->assertEquals($time, $report->getTimeDelivered());
  }

  /**
   * Creates an SMS delivery report for testing.
   *
   * @return \Drupal\sms\Message\SmsDeliveryReportInterface
   *   A delivery report for testing.
   */
  abstract protected function createDeliveryReport(): SmsDeliveryReportInterface;

}
