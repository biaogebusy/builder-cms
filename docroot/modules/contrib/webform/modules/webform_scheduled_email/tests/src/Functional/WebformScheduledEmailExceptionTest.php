<?php

namespace Drupal\Tests\webform_scheduled_email\Functional;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\webform_node\Functional\WebformNodeBrowserTestBase;
use Drupal\webform\Entity\Webform;

/**
 * Tests that a thrown exception during send does not abort the cron batch.
 *
 * Reproduces the production incident: a recipient that passes Webform/Drupal
 * validation but is rejected by the mail transport throws inside cronSend().
 * Pre-patch the uncaught throw aborts cronSend() before the post-loop DELETE,
 * so every already-processed scheduled email stays queued and re-sends on every
 * subsequent cron. The patch wraps the send in try/catch so the batch finishes,
 * the records are deleted, and the failure is logged.
 *
 * @group webform_scheduled_email
 */
class WebformScheduledEmailExceptionTest extends WebformNodeBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'webform',
    'webform_scheduled_email',
    'webform_scheduled_email_exception_test',
    'webform_node',
    'dblog',
  ];

  /**
   * One throwing send must not abort the batch or leave the queue stuck.
   */
  public function testCronSurvivesSendException() {
    // Route all outgoing mail through the throwing/collecting test backend.
    $this->config('system.mail')
      ->set('interface.default', 'throwing_test_mail')
      ->save();

    $webform = Webform::load('test_scheduled_email_exception');

    /** @var \Drupal\webform_scheduled_email\WebformScheduledEmailManagerInterface $manager */
    $manager = \Drupal::service('webform_scheduled_email.manager');

    // Queue two scheduled emails, both due in the past (handler days = -1):
    // one whose recipient makes the backend throw, one that sends fine. Both
    // addresses are syntactically valid so they survive Webform's own
    // recipient validation and actually reach the mail backend.
    $this->postSubmission($webform, ['recipient' => 'throw-me@example.com']);
    $this->postSubmission($webform, ['recipient' => 'ok@example.com']);
    $this->assertEquals(2, $manager->total(), 'Two scheduled emails are queued.');

    // Run the scheduled-email cron. Pre-patch, the throw aborts cronSend()
    // before the post-loop DELETE, leaving both rows queued to re-send.
    $stats = $manager->cron();

    // 1. Cron completed without an uncaught exception propagating out.
    $this->assertIsArray($stats);

    // 2. DECISIVE: the queue is fully cleared even though one send threw.
    //    This assertion FAILS on an unpatched module (the rows persist).
    $this->assertEquals(0, $manager->total(), 'All scheduled rows were deleted despite the thrown send.');

    // 3. The valid sibling email was still delivered; the bad one never was.
    $recipients = array_column(\Drupal::state()->get('system.test_mail_collector') ?: [], 'to');
    $this->assertContains('ok@example.com', $recipients, 'The valid sibling email was still sent.');
    $this->assertNotContains('throw-me@example.com', $recipients, 'The throwing recipient was never delivered.');

    // 4. The catch path logged an ERROR to the webform_scheduled_email channel.
    $logged = (bool) \Drupal::database()->select('watchdog', 'w')
      ->fields('w', ['wid'])
      ->condition('type', 'webform_scheduled_email')
      ->condition('severity', RfcLogLevel::ERROR)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    $this->assertTrue($logged, 'An error was logged for the failed scheduled email.');

    // 5. No re-send loop: a second cron sends nothing, queue stays empty.
    $stats_again = $manager->cron();
    $this->assertEquals(0, $manager->total(), 'Queue remains empty after a second cron.');
    $this->assertEquals(0, $stats_again[$manager::EMAIL_SENT] ?? 0, 'Second cron re-sent nothing.');
  }

}
