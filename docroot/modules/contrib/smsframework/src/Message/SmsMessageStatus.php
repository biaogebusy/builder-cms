<?php

declare(strict_types = 1);

namespace Drupal\sms\Message;

/**
 * Defines common SMS Framework message state.
 */
class SmsMessageStatus {

  /**
   * Message could not be processed due to an unknown problem with the gateway.
   */
  public const ERROR = 'error';

}
