<?php

declare(strict_types=1);

namespace Drupal\Tests\sms\Unit\Message;

use Drupal\sms\Message\SmsMessageResult;
use Drupal\sms\Message\SmsMessageResultInterface;
use Drupal\Tests\sms\Functional\SmsFrameworkMessageResultTestTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for results.
 *
 * @group SMS Framework
 * @coversDefaultClass \Drupal\sms\Message\SmsMessageResult
 */
final class SmsFrameworkResultUnitTest extends UnitTestCase {

  use SmsFrameworkMessageResultTestTrait;

  protected function createMessageResult(): SmsMessageResultInterface {
    return new SmsMessageResult();
  }

}
