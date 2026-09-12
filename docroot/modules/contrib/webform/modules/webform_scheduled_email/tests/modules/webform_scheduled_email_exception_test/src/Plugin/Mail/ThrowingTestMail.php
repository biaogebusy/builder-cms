<?php

namespace Drupal\webform_scheduled_email_exception_test\Plugin\Mail;

use Drupal\Core\Mail\MailInterface;

/**
 * A mail backend that throws on a sentinel recipient, otherwise collects.
 *
 * Models the SendGrid TypeException thrown when a recipient address passes
 * Drupal/Webform validation but fails the mail transport's stricter check, so
 * a test can verify that webform_scheduled_email cron survives a thrown send.
 *
 * Core's TestPhpMailFailure only returns FALSE, which does NOT reproduce the
 * bug (a FALSE return still lets the batch DELETE run); we need a real throw.
 *
 * @Mail(
 *   id = "throwing_test_mail",
 *   label = @Translation("Throwing test mailer"),
 *   description = @Translation("Throws on a sentinel recipient; collects others.")
 * )
 */
class ThrowingTestMail implements MailInterface {

  /**
   * {@inheritdoc}
   */
  public function format(array $message) {
    if (is_array($message['body'])) {
      $message['body'] = implode("\n\n", $message['body']);
    }
    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function mail(array $message) {
    // Throw for the sentinel recipient, mirroring the SendGrid client-side
    // TypeException. \Exception is the type the patch's catch block handles.
    if (str_contains($message['to'] ?? '', 'throw-me')) {
      throw new \Exception(sprintf('"%s" must be a valid email address.', $message['to']));
    }
    // Otherwise behave like core's TestMailCollector so a valid sibling email
    // can be asserted as delivered.
    $collected = \Drupal::state()->get('system.test_mail_collector') ?: [];
    $collected[] = $message;
    \Drupal::state()->set('system.test_mail_collector', $collected);
    return TRUE;
  }

}
