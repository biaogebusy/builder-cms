<?php

declare(strict_types = 1);

namespace Drupal\sms\Message;

/**
 * Defines states for SMS message reports.
 */
final class SmsMessageReportStatus extends SmsMessageStatus {

  /**
   * Message was queued for sending.
   */
  public const QUEUED = 'queued';

  /**
   * Message was successfully delivered to the recipient.
   */
  public const DELIVERED = 'delivered';

  /**
   * Message expired and was not sent.
   */
  public const EXPIRED = 'expired';

  /**
   * Message was rejected by the gateway.
   */
  public const REJECTED = 'rejected';

  /**
   * Indicates a recipient of the message is invalid.
   */
  public const INVALID_RECIPIENT = 'invalid_recipient';

  /**
   * Content of message invalid or not supported by gateway.
   */
  public const CONTENT_INVALID = 'content_invalid';

}
