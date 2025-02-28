<?php

declare(strict_types = 1);

namespace Drupal\sms\Message;

/**
 * Defines states for SMS message results.
 *
 * Usually setting a status on a result indicates something went wrong with the
 * entire transaction.
 */
class SmsMessageResultStatus extends SmsMessageStatus {

  /**
   * Account error.
   *
   * Some configuration is required that can only be resolved on the gateway
   * end.
   */
  const ACCOUNT_ERROR = 'account_error';

  /**
   * Too many requests.
   */
  const EXCESSIVE_REQUESTS = 'flooded';

  /**
   * Message could not be processed due to low credit.
   */
  const NO_CREDIT = 'no_credit';

  /**
   * Indicates the sender ID is invalid.
   */
  const INVALID_SENDER = 'invalid_sender';

  /**
   * Failed to authenticate with gateway.
   */
  const AUTHENTICATION = 'authentication';

  /**
   * Invalid or missing request parameters.
   */
  const PARAMETERS = 'parameters';

}
