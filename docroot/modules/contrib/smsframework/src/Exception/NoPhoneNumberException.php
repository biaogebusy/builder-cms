<?php

declare(strict_types = 1);

namespace Drupal\sms\Exception;

/**
 * Thrown if code incorrectly assumes an entity has a phone number.
 *
 * Call \Drupal::service('sms.phone_number')->getPhoneNumbers() if unsure.
 */
class NoPhoneNumberException extends SmsException {}
