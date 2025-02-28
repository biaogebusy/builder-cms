<?php

declare(strict_types = 1);

namespace Drupal\Tests\sms_devel\Functional;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\sms\Entity\SmsGatewayInterface;
use Drupal\Tests\sms\Functional\SmsFrameworkBrowserTestBase;
use Drupal\sms\Entity\SmsMessage;
use Drupal\sms\Direction;

/**
 * Tests the message form.
 *
 * @group SMS Framework
 */
final class SmsDevelMessageTest extends SmsFrameworkBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['sms_devel'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * A memory gateway.
   *
   * @var \Drupal\sms\Entity\SmsGatewayInterface
   */
  protected SmsGatewayInterface $gateway;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->drupalCreateUser(['sms_devel form']);
    $this->drupalLogin($user);

    $this->gateway = $this->createMemoryGateway();
    $this->setFallbackGateway($this->gateway);
  }

  /**
   * Tests sending functionality skipping queue.
   */
  public function testSendSkipQueue(): void {
    $edit['number'] = $this->randomPhoneNumbers(1)[0];
    $edit['message'] = $this->randomString();
    $edit['skip_queue'] = TRUE;

    $this->drupalGet(Url::fromRoute('sms_devel.message'));
    $this->submitForm($edit, t('Send'));
    $this->assertSession()->responseContains('Message was processed, 1 delivery reports were generated.');

    $messages = $this->getTestMessages($this->gateway);
    $this->assertCount(1, $messages);
    $this->assertEquals($edit['message'], $messages[0]->getMessage());
  }

  /**
   * Tests sending functionality entering queue.
   */
  public function testSendNoSkipQueue(): void {
    $edit['number'] = $this->randomPhoneNumbers(1)[0];
    $edit['message'] = $this->randomString();
    $edit['skip_queue'] = FALSE;

    $this->drupalGet(Url::fromRoute('sms_devel.message'));
    $this->submitForm($edit, t('Send'));
    $this->assertSession()->responseContains('Message added to the outgoing queue.');

    $messages = SmsMessage::loadMultiple();
    $message = reset($messages);
    $this->assertEquals($edit['message'], $message->getMessage(), 'Message is same');
    $this->assertEquals(Direction::OUTGOING, $message->getDirection(), 'Message is outgoing');
  }

  /**
   * Tests receiving functionality skipping queue.
   */
  public function testReceiveSkipQueue(): void {
    $edit['number'] = $this->randomPhoneNumbers(1)[0];
    $edit['message'] = $this->randomString();
    $edit['gateway'] = $this->gateway->id();
    $edit['skip_queue'] = TRUE;

    $this->drupalGet(Url::fromRoute('sms_devel.message'));
    $this->submitForm($edit, t('Receive'));
    $this->assertSession()->responseContains('Message was processed, 1 delivery reports were generated.');

    $this->assertEquals($edit['message'], sms_test_gateway_get_incoming()['message']);
  }

  /**
   * Tests receiving functionality entering queue.
   */
  public function testReceiveNoSkipQueue(): void {
    $edit['number'] = $this->randomPhoneNumbers(1)[0];
    $edit['message'] = $this->randomString();
    $edit['gateway'] = $this->gateway->id();
    $edit['skip_queue'] = FALSE;

    $this->drupalGet(Url::fromRoute('sms_devel.message'));
    $this->submitForm($edit, t('Receive'));
    $this->assertSession()->responseContains('Message added to the incoming queue.');

    $messages = SmsMessage::loadMultiple();
    $message = reset($messages);
    $this->assertEquals($edit['message'], $message->getMessage(), 'Message is same');
    $this->assertEquals(Direction::INCOMING, $message->getDirection(), 'Message is incoming');
  }

  /**
   * Tests receiving with no selected gateway.
   */
  public function testReceiveGatewayInvalid(): void {
    $edit['gateway'] = '';

    $this->drupalGet(Url::fromRoute('sms_devel.message'));
    $this->submitForm($edit, t('Receive'));
    $this->assertSession()->responseContains('Gateway must be selected if receiving a message.');
  }

  /**
   * Tests tagging message as automated.
   */
  public function testAutomated(): void {
    $edit['number'] = $this->randomPhoneNumbers(1)[0];
    $edit['message'] = $this->randomString();
    $edit['skip_queue'] = FALSE;
    $edit['automated'] = FALSE;

    $this->drupalGet(Url::fromRoute('sms_devel.message'));
    $this->submitForm($edit, t('Send'));

    $messages = SmsMessage::loadMultiple();
    $message = reset($messages);
    $this->assertFalse($message->isAutomated(), 'Message is not automated');
  }

  /**
   * Tests adding send date.
   */
  public function testDate(): void {
    $edit['number'] = $this->randomPhoneNumbers(1)[0];
    $edit['message'] = $this->randomString();
    $edit['skip_queue'] = FALSE;

    $value = '2005-11-25 22:03:58';
    $date = new DrupalDateTime($value, 'UTC');
    // The user inputs field values in its own timezone, then it is auto
    // converted on field submission to UTC.
    $date_user = $date;
    $date_user->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    $edit['send_on[date]'] = $date_user->format('Y-m-d');
    $edit['send_on[time]'] = $date_user->format('H:i:s');
    $this->drupalGet(Url::fromRoute('sms_devel.message'));
    $this->submitForm($edit, t('Send'));

    $messages = SmsMessage::loadMultiple();
    $message = reset($messages);
    $this->assertEquals($date->format('U'), $message->getSendTime(), 'Message has send time.');
  }

  /**
   * Tests error shown if gateway found for message.
   */
  public function testNoFallbackGateway(): void {
    $this->setFallbackGateway(NULL);

    $edit['number'] = $this->randomPhoneNumbers(1)[0];
    $edit['message'] = $this->randomString();
    $edit['skip_queue'] = TRUE;

    $this->drupalGet(Url::fromRoute('sms_devel.message'));
    $this->submitForm($edit, t('Send'));
    $this->assertSession()->responseContains('Message could not be sent');

    $messages = $this->getTestMessages($this->gateway);
    $this->assertCount(0, $messages, 'No messages sent.');
  }

  /**
   * Tests verbose message output.
   */
  public function testVerboseReports(): void {
    $edit['gateway'] = $this->gateway->id();
    $edit['number'] = $this->randomPhoneNumbers(1)[0];
    $edit['message'] = $this->randomString();
    $edit['skip_queue'] = TRUE;
    $edit['verbose'] = TRUE;

    $this->drupalGet(Url::fromRoute('sms_devel.message'));
    $this->submitForm($edit, t('Send'));
    $this->assertSession()->responseContains('Message was processed, 1 delivery reports were generated.');

    $first_row = '#edit-results > tbody > tr:nth-child(1)';

    // Result.
    $selector = $first_row . ' > td:nth-child(1)';
    $this->assertSession()->elementTextContains('css', $selector, '#0');

    // Error.
    $selector = $first_row . ' > td:nth-child(2)';
    $this->assertSession()->elementTextContains('css', $selector, 'Success');

    // Error Message.
    $selector = $first_row . ' > td:nth-child(3)';
    $this->assertSession()->elementTextContains('css', $selector, 'Undefined');

    // Credits Used.
    $selector = $first_row . ' > td:nth-child(4)';
    $this->assertSession()->elementTextContains('css', $selector, 'Undefined');

    // Credits Balance.
    $selector = $first_row . ' > td:nth-child(5)';
    $this->assertSession()->elementTextContains('css', $selector, 'Undefined');

    $message = $this->getLastTestMessage($this->gateway);
    $report = $this->getTestMessageReports($this->gateway)[0];
    $this->assertEquals($edit['number'], $report->getRecipient());

    $first_row_first_report = '#edit-results > tbody > tr:nth-child(2) > td > table > tbody > tr:nth-child(1)';

    // Recipient.
    $selector = $first_row_first_report . ' > td:nth-child(1)';
    $this->assertSession()->elementTextContains('css', $selector, $report->getRecipient());

    // Message ID.
    $selector = $first_row_first_report . ' > td:nth-child(2)';
    $this->assertSession()->elementTextContains('css', $selector, $report->getMessageId());

    // Status.
    $selector = $first_row_first_report . ' > td:nth-child(3)';
    $this->assertSession()->elementTextContains('css', $selector, $report->getStatus());

    // Status Message.
    $selector = $first_row_first_report . ' > td:nth-child(4)';
    $this->assertSession()->elementTextContains('css', $selector, $report->getStatusMessage());

    // Time Delivered.
    $date = DrupalDateTime::createFromTimestamp($report->getTimeDelivered());
    $selector = $first_row_first_report . ' > td:nth-child(5)';
    $this->assertSession()->elementTextContains('css', $selector, $date->format('c'));

    // Time Queued.
    $date = DrupalDateTime::createFromTimestamp($report->getTimeQueued());
    $selector = $first_row_first_report . ' > td:nth-child(6)';
    $this->assertSession()->elementTextContains('css', $selector, $date->format('c'));
  }

}
