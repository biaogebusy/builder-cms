<?php

declare(strict_types = 1);

namespace Drupal\sms\Message;

/**
 * Defines states for SMS message reports.
 */
class SmsMessageReportStatus extends SmsMessageStatus {

  /**
   * Message was queued for sending.
   */
  const QUEUED = 'queued';

  /**
   * Message was successfully delivered to the recipient.
   */
  const DELIVERED = 'delivered';

  /**
   * Message expired and was not sent.
   */
  const EXPIRED = 'expired';

  /**
   * Message was rejected by the gateway.
   */
  const REJECTED = 'rejected';

  /**
   * Indicates a recipient of the message is invalid.
   */
  const INVALID_RECIPIENT = 'invalid_recipient';

  /**
   * Content of message invalid or not supported by gateway.
   */
  const CONTENT_INVALID = 'content_invalid';

}
