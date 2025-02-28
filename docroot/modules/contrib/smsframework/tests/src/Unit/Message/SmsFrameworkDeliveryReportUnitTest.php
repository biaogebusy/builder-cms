<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Unit\Message;

use Drupal\sms\Message\SmsDeliveryReportInterface;
use Drupal\Tests\sms\Functional\SmsFrameworkDeliveryReportTestTrait;
use Drupal\Tests\UnitTestCase;
use Drupal\sms\Message\SmsDeliveryReport;

/**
 * Unit tests for delivery reports.
 *
 * @group SMS Framework
 * @coversDefaultClass \Drupal\sms\Message\SmsDeliveryReport
 */
final class SmsFrameworkDeliveryReportUnitTest extends UnitTestCase {

  use SmsFrameworkDeliveryReportTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function createDeliveryReport(): SmsDeliveryReportInterface {
    return new SmsDeliveryReport();
  }

}
