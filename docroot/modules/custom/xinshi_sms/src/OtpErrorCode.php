<?php

namespace Drupal\xinshi_sms;

/**
 * Stable, machine-readable reasons returned by the OTP REST resources.
 *
 * Clients map these codes onto their own localized copy — see BACKEND_CODE_KEYS
 * in the Angular login component. The `message` shipped alongside a code is
 * English-only debug text and must never be shown to end users.
 */
final class OtpErrorCode {

  /**
   * No phone number was submitted.
   */
  const PHONE_REQUIRED = 'phone_required';

  /**
   * The submitted phone number is not a valid mainland China mobile number.
   */
  const PHONE_INVALID = 'phone_invalid';

  /**
   * The number has no account and auto-registration was not requested.
   */
  const PHONE_NOT_REGISTERED = 'phone_not_registered';

  /**
   * The submitted code is wrong or has expired.
   */
  const CODE_INCORRECT = 'code_incorrect';

  /**
   * Too many code requests for this number or from this client.
   */
  const CODE_SEND_THROTTLED = 'code_send_throttled';

  /**
   * The SMS gateway refused or failed to queue the message.
   */
  const CODE_SEND_FAILED = 'code_send_failed';

  /**
   * The code was accepted but no OAuth2 token could be issued.
   */
  const OAUTH_FAILED = 'oauth_failed';

  /**
   * Anything unexpected — the client shows a generic message for this one.
   */
  const SERVER_ERROR = 'server_error';

  /**
   * Builds a failure payload.
   *
   * @param string $code
   *   One of this class' constants.
   * @param string $message
   *   English debug text, for logs and developers only.
   *
   * @return array
   *   The response body.
   */
  public static function failure($code, $message = '') {
    return [
      'status' => FALSE,
      'code' => $code,
      'message' => (string) $message,
    ];
  }

  /**
   * Picks the code that explains why a phone number failed validation.
   *
   * @param string $mobile_number
   *   The submitted phone number.
   *
   * @return string
   *   One of this class' constants.
   */
  public static function phoneFailure($mobile_number) {
    return empty($mobile_number) ? self::PHONE_REQUIRED : self::PHONE_INVALID;
  }

}
