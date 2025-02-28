<?php

declare(strict_types = 1);

namespace Drupal\sms_user;

use Drupal\sms\Message\SmsMessageInterface;

/**
 * Defines interface for the account registration service.
 */
interface AccountRegistrationInterface {

  /**
   * Process an incoming SMS to see if a new account should be created.
   *
   * @param \Drupal\sms\Message\SmsMessageInterface $sms_message
   *   An incoming SMS message.
   */
  public function createAccount(SmsMessageInterface $sms_message);

}
