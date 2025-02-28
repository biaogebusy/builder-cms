<?php

namespace Drupal\mailsystem_test\Plugin\Mail;

use Drupal\Core\Mail\MailInterface;

/**
 * Provides a 'Dummy' plugin to send emails.
 *
 * @Mail(
 *   id = "mailsystem_dummy",
 *   label = @Translation("Dummy Mail-Plugin"),
 *   description = @Translation("Dummy Plugin to debug the complete email on formatting and sending.")
 * )
 */
class Dummy implements MailInterface {

  /**
   * {@inheritdoc}
   */
  public function format(array $message) {
    // TODO: Implement format() method.
    \dump([
      'Subject' => $message['subject'],
      'Body' => $message['body'],
      'Headers' => $message['headers'],
    ], 'Dummy: format()');
    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function mail(array $message) {
    // TODO: Implement mail() method.
    \dump([
      'Subject' => $message['subject'],
      'Body' => $message['body'],
      'Headers' => $message['headers'],
    ], 'Dummy: mail()');
    return TRUE;
  }

}
