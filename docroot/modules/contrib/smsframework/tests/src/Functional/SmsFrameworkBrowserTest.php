<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms\Functional;

use Drupal\Core\Url;
use Drupal\sms\Entity\SmsMessage;
use Drupal\sms\Direction;

/**
 * Integration tests for the SMS Framework.
 *
 * @group SMS Framework
 */
final class SmsFrameworkBrowserTest extends SmsFrameworkBrowserTestBase {

  /**
   * Tests queue statistics located on Drupal report page.
   */
  public function testQueueReport(): void {
    $gateway = $this->createMemoryGateway();

    /** @var \Drupal\sms\Provider\SmsProviderInterface $provider */
    $provider = \Drupal::service('sms.provider');

    /** @var \Drupal\sms\Entity\SmsMessageInterface $sms_message */
    $sms_message = SmsMessage::create();
    $sms_message
      ->setMessage($this->randomString())
      ->addRecipients($this->randomPhoneNumbers());

    for ($i = 0; $i < 2; $i++) {
      $clone = $sms_message->createDuplicate()
        ->setDirection(Direction::INCOMING)
        ->setGateway($gateway);
      $clone->setResult($this->createMessageResult($clone));
      $provider->queue($clone);
    }
    for ($i = 0; $i < 4; $i++) {
      $clone = $sms_message->createDuplicate()
        ->setDirection(Direction::OUTGOING);
      $provider->queue($clone);
    }

    $this->drupalLogin($this->rootUser);
    $this->drupalGet(Url::fromRoute('system.status'));

    $this->assertSession()->responseContains('There are 2 messages in the incoming queue.');
    $this->assertSession()->responseContains('There are 4 messages in the outgoing queue.');
  }

}
