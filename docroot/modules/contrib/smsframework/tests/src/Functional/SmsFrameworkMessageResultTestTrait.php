<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Functional;

use Drupal\sms\Exception\SmsException;
use Drupal\sms\Message\SmsDeliveryReport;
use Drupal\sms\Message\SmsDeliveryReportInterface;
use Drupal\sms\Message\SmsMessageResultInterface;

/**
 * Provides common tests for SmsMessageResult object and entity classes.
 */
trait SmsFrameworkMessageResultTestTrait {

  /**
   * Tests error.
   *
   * @covers ::getError
   * @covers ::setError
   */
  public function testError(): void {
    $result = $this->createMessageResult();
    static::assertNull($result->getError(), 'Default value is NULL');

    $error = $this->getRandomGenerator()->string();
    $return = $result->setError($error);

    static::assertTrue($return instanceof SmsMessageResultInterface);
    static::assertEquals($error, $result->getError());
  }

  /**
   * Tests error message.
   *
   * @covers ::getErrorMessage
   * @covers ::setErrorMessage
   */
  public function testErrorMessage(): void {
    $result = $this->createMessageResult();
    static::assertEquals('', $result->getErrorMessage(), 'Default value is empty string');

    $error_message = $this->getRandomGenerator()->string();
    $return = $result->setErrorMessage($error_message);

    static::assertTrue($return instanceof SmsMessageResultInterface);
    static::assertEquals($error_message, $result->getErrorMessage());
  }

  /**
   * Tests get report for a recipient.
   *
   * @covers ::getReport
   */
  public function testGetReport(): void {
    $result = $this->createMessageResult();
    $recipient = '123123123';
    static::assertNull($result->getReport($recipient), 'No report found');

    $report = (new SmsDeliveryReport())
      ->setRecipient($recipient);
    $result->setReports([$report]);

    static::assertTrue($result->getReport($recipient) instanceof SmsDeliveryReportInterface, 'Report found');
  }

  /**
   * Tests setting and getting the list of reports.
   *
   * @covers ::getReports
   * @covers ::setReports
   */
  public function testReports(): void {
    $result = $this->createMessageResult();
    $recipient = '123123123';

    $report = (new SmsDeliveryReport())
      ->setRecipient($recipient);
    $return = $result->setReports([$report]);
    static::assertTrue($return instanceof SmsMessageResultInterface);

    $reports = $result->getReports();
    static::assertCount(1, $reports);
    static::assertTrue($reports[0] instanceof SmsDeliveryReportInterface);

    // Verify that a second ::setReports() call clears what was there before.
    $report2 = (new SmsDeliveryReport())
      ->setRecipient('2345678901');
    $result->setReports([$report2]);

    $reports = $result->getReports();
    static::assertCount(1, $reports);
  }

  /**
   * Tests adding a report to the list of reports.
   *
   * @covers ::addReport
   */
  public function testAddReport(): void {
    $result = $this->createMessageResult();

    static::assertCount(0, $result->getReports(), 'There are zero reports.');

    $report = (new SmsDeliveryReport())
      ->setRecipient('123123123');

    $return = $result->addReport($report);
    static::assertTrue($return instanceof SmsMessageResultInterface, 'Return type is a result object');

    static::assertCount(1, $result->getReports(), 'There is one report.');
  }

  /**
   * Tests credits balance.
   *
   * @covers ::getCreditsBalance
   * @covers ::setCreditsBalance
   */
  public function testCreditsBalance(): void {
    $result = $this->createMessageResult();
    static::assertNull($result->getCreditsBalance(), 'No credit balance set');

    $balance = 13.37;
    $return = $result->setCreditsBalance($balance);
    static::assertTrue($return instanceof SmsMessageResultInterface);
    static::assertEquals($balance, $result->getCreditsBalance());
  }

  /**
   * Tests credits balance set is wrong type.
   *
   * @covers ::setCreditsBalance
   */
  public function testCreditsBalanceIncorrectType(): void {
    $balance = 'foobar';
    $result = $this->createMessageResult();

    $this->expectException(SmsException::class);
    $this->expectExceptionMessage('Credit balance set is a string');
    $result->setCreditsBalance($balance);
  }

  /**
   * Tests credits used.
   *
   * @covers ::getCreditsUsed
   * @covers ::setCreditsUsed
   */
  public function testCreditsUsed(): void {
    $result = $this->createMessageResult();
    static::assertNull($result->getCreditsUsed(), 'No credits used set');

    $used = 13.37;
    $return = $result->setCreditsUsed($used);
    static::assertTrue($return instanceof SmsMessageResultInterface);
    static::assertEquals($used, $result->getCreditsUsed());
  }

  /**
   * Tests credits used set is wrong type.
   *
   * @covers ::setCreditsUsed
   */
  public function testCreditsUsedIncorrectType(): void {
    $used = 'foobar';
    $result = $this->createMessageResult();

    $this->expectException(SmsException::class);
    $this->expectExceptionMessage('Credit used is a string');
    $result->setCreditsUsed($used);
  }

  /**
   * Creates a message result for testing.
   *
   * @return \Drupal\sms\Message\SmsMessageResultInterface
   *   A result for testing.
   */
  abstract protected function createMessageResult(): SmsMessageResultInterface;

}
