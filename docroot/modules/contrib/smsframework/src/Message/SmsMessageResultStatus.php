<?php

declare(strict_types = 1);

namespace Drupal\sms\Message;

/**
 * Defines states for SMS message results.
 *
 * Usually setting a status on a result indicates something went wrong with the
 * entire transaction.
 */
final class SmsMessageResultStatus extends SmsMessageStatus {

  /**
   * Account error.
   *
   * Some configuration is required that can only be resolved on the gateway
   * end.
   */
  public const ACCOUNT_ERROR = 'account_error';

  /**
   * Too many requests.
   */
  public const EXCESSIVE_REQUESTS = 'flooded';

  /**
   * Message could not be processed due to low credit.
   */
  public const NO_CREDIT = 'no_credit';

  /**
   * Indicates the sender ID is invalid.
   */
  public const INVALID_SENDER = 'invalid_sender';

  /**
   * Failed to authenticate with gateway.
   */
  public const AUTHENTICATION = 'authentication';

  /**
   * Invalid or missing request parameters.
   */
  public const PARAMETERS = 'parameters';

}
