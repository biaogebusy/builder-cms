<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\sms\Functional\SmsFrameworkTestTrait;

/**
 * Base class for SMS Framework unit tests.
 */
abstract class SmsFrameworkKernelBase extends KernelTestBase {

  use SmsFrameworkTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('sms');
  }

}
