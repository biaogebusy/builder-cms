<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Unit\Message;

use Drupal\Component\Uuid\Php;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\sms\Message\SmsMessage;
use Drupal\sms\Message\SmsMessageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\sms\Functional\SmsFrameworkMessageTestTrait;

/**
 * Unit Tests for SmsMessage.
 *
 * @group SMS Framework
 * @coversDefaultClass \Drupal\sms\Message\SmsMessage
 */
final class SmsMessageTest extends UnitTestCase {

  use SmsFrameworkMessageTestTrait;

  /**
   * Create a SMS message object for testing.
   *
   * @return \Drupal\sms\Message\SmsMessageInterface
   *   A SMS message object for testing.
   */
  protected function createSmsMessage(): SmsMessageInterface {
    return new TestSmsMessage();
  }

}

/**
 * Mock class for testing.
 */
final class TestSmsMessage extends SmsMessage {

  /**
   * {@inheritdoc}
   */
  protected function uuidGenerator(): UuidInterface {
    return new Php();
  }

}
