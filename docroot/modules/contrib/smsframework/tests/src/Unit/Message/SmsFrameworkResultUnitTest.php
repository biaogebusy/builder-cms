<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Unit\Message;

use Drupal\sms\Message\SmsMessageResultInterface;
use Drupal\Tests\sms\Functional\SmsFrameworkMessageResultTestTrait;
use Drupal\Tests\UnitTestCase;
use Drupal\sms\Message\SmsMessageResult;

/**
 * Unit tests for results.
 *
 * @group SMS Framework
 * @coversDefaultClass \Drupal\sms\Message\SmsMessageResult
 */
final class SmsFrameworkResultUnitTest extends UnitTestCase {

  use SmsFrameworkMessageResultTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function createMessageResult(): SmsMessageResultInterface {
    return new SmsMessageResult();
  }

}
