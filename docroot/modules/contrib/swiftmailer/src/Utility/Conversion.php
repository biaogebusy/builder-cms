<?php

namespace Drupal\swiftmailer\Utility;

use Swift_Message;

/**
 * @todo
 */
class Conversion {

  const SWIFTMAILER_DATE_PATTERN = '(Mon|Tue|Wed|Thu|Fri|Sat|Sun), [0-9]{2} (Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) [0-9]{4} (2[0-3]|[01][0-9]):([0-5][0-9]):([0-5][0-9]) (\+|\-)([01][0-2])([0-5][0-9])';

  const SWIFTMAILER_MAILBOX_PATTERN = '(^.*\<.*@.*\>$|^.*@.*$)';

  /**
   * Determines the header type based on the a header key and value.
   *
   * @param string $key
   *   The header key.
   * @param string $value
   *   The header value.
   *
   * @return string
   *   The header type as determined based on the provided header key
   *   and value.
   */
  public static function swiftmailer_get_headertype($key, $value) {

    if (static::swiftmailer_is_id_header($key, $value)) {
      return SWIFTMAILER_HEADER_ID;
    }

    if (static::swiftmailer_is_path_header($key, $value)) {
      return SWIFTMAILER_HEADER_PATH;
    }

    if (static::swiftmailer_is_mailbox_header($key, $value)) {
      return SWIFTMAILER_HEADER_MAILBOX;
    }

    if (static::swiftmailer_is_date_header($key, $value)) {
      return SWIFTMAILER_HEADER_DATE;
    }

    if (static::swiftmailer_is_parameterized_header($key, $value)) {
      return SWIFTMAILER_HEADER_PARAMETERIZED;
    }

    return SWIFTMAILER_HEADER_TEXT;

  }

  /**
   * Adds a text header to a message.
   *
   * @param \Swift_Message $message
   *   The message which the text header is to be added to.
   * @param string $key
   *   The header key.
   * @param string $value
   *   The header value.
   */
  public static function swiftmailer_add_text_header(Swift_Message $message, $key, $value) {

    // Remove any already existing header identified by the provided key.
    static::swiftmailer_remove_header($message, $key);

    // Add the header.
    $message->getHeaders()->addTextHeader($key, $value);
  }

  /**
   * Checks whether a header is a parameterized header.
   *
   * @see http://swift_mailer.org/docs/header-parameterized
   *
   * @param string $key
   *   The header key.
   * @param string $value
   *   The header value.
   *
   * @return bool
   *   TRUE if the provided header is a parameterized header,
   *   and otherwise FALSE.
   */
  public static function swiftmailer_is_parameterized_header($key, $value) {
    // Assume the header is parameterized if there is at least one ;, always
    // treat the Content-Type header as parameterized.
    if (preg_match('/;/', $value) || $key == 'Content-Type') {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Adds a parameterized header to a message.
   *
   * @param \Swift_Message $message
   *   The message which the parameterized header is to be added to.
   * @param string $key
   *   The header key.
   * @param string $value
   *   The header value.
   */
  public static function swiftmailer_add_parameterized_header(Swift_Message $message, $key, $value) {

    // Remove any already existing header identified by the provided key.
    static::swiftmailer_remove_header($message, $key);

    // Define variables to hold the header's value and parameters.
    $header_value = NULL;
    $header_parameters = [];

    // Split the provided value by ';' (semicolon), which we assume is the
    // character is used to separate the parameters.
    $parameter_pairs = explode(';', $value);

    // Iterate through the extracted parameters, and prepare each of them to be
    // added to a parameterized message header. There should be a single text
    // parameter and one or more key/value parameters in the provided header
    // value. We assume that a '=' (equals) character is used to separate the
    // key and value for each of the parameters.
    foreach ($parameter_pairs as $parameter_pair) {

      // Find out whether the current parameter pair really is a parameter
      // pair or just a single value.
      if (preg_match('/=/', $parameter_pair) > 0) {

        // Split the parameter so that we can access the parameter's key and
        // value separately.
        $parameter_pair = explode('=', $parameter_pair);

        // Validate that the parameter has been split in two, and that both
        // the parameter's key and value is accessible. If that is the case,
        // then add the current parameter's key and value to the array which
        // holds all parameters to be added to the current header.
        if (!empty($parameter_pair[0]) && !empty($parameter_pair[1])) {
          $header_parameters[trim($parameter_pair[0])] = trim($parameter_pair[1]);
        }

      }
      else {
        $header_value = trim($parameter_pair);
      }
    }

    // Add the parameterized header.
    $message->getHeaders()
      ->addParameterizedHeader($key, $header_value, $header_parameters);

  }

  /**
   * Checks whether a header is a date header.
   *
   * @see http://swift_mailer.org/docs/header-date
   *
   * @param string $key
   *   The header key.
   * @param string $value
   *   The header value.
   *
   * @return bool
   *   TRUE if the provided header is a date header, and otherwise FALSE.
   */
  public static function swiftmailer_is_date_header($key, $value) {
    if (preg_match('/' . static::SWIFTMAILER_DATE_PATTERN . '/', $value)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Adds a date header to a message.
   *
   * @param \Swift_Message $message
   *   The message which the date header is to be added to.
   * @param string $key
   *   The header key.
   * @param string $value
   *   The header value.
   */
  public static function swiftmailer_add_date_header(Swift_Message $message, $key, $value) {

    // Remove any already existing header identified by the provided key.
    static::swiftmailer_remove_header($message, $key);

    // Add the header.
    $message->getHeaders()->addDateHeader($key, $value);
  }

  /**
   * Checks whether a header is a mailbox header.
   *
   * It is difficult to distinguish id, mailbox and path headers from each other
   * as they all may very well contain the exact same value. This public static function simply
   * checks whether the header key equals to 'Message-ID' to determine if the
   * header is a path header.
   *
   * @see http://swift_mailer.org/docs/header-mailbox
   *
   * @param string $key
   *   The header key.
   * @param string $value
   *   The header value.
   *
   * @return bool
   *   TRUE if the provided header is a mailbox header, and otherwise FALSE.
   */
  public static function swiftmailer_is_mailbox_header($key, $value) {
    // Remove line breaks, which are legitimate within a mime header.
    // A line break within the header does not mean
    // a line break within the header value.
    // See RFC 2047, the related RFCs.
    // @see https://www.drupal.org/project/drupal/issues/84883.
    $value = str_replace(["\n", "\r"], '', $value);

    if (preg_match('/' . static::SWIFTMAILER_MAILBOX_PATTERN . '/', $value)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Adds a mailbox header to a message.
   *
   * @param \Swift_Message $message
   *   The message which the mailbox header is to be added to.
   * @param string $key
   *   The header key.
   * @param string $value
   *   The header value.
   */
  public static function swiftmailer_add_mailbox_header(Swift_Message $message, $key, $value) {

    // Remove any already existing header identified by the provided key.
    static::swiftmailer_remove_header($message, $key);

    // Parse mailboxes.
    $mailboxes = static::swiftmailer_parse_mailboxes($value);

    // Add the header.
    $message->getHeaders()->addMailboxHeader($key, $mailboxes);
  }

  /**
   * Checks whether a header is an id header.
   *
   * It is difficult to distinguish id, mailbox and path headers from each other
   * as they all may very well contain the exact same value. This public static function simply
   * checks whether the header key equals to 'Message-ID' to determine if the
   * header is a path header.
   *
   * @see http://swift_mailer.org/docs/header-id
   *
   * @param string $key
   *   The header key.
   * @param string $value
   *   The header value.
   *
   * @return bool
   *   TRUE if the provided header is an id header, and otherwise FALSE.
   */
  public static function swiftmailer_is_id_header($key, $value) {
    if ($key == 'Message-ID' && \Drupal::service('email.validator')->isValid($value)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Adds an id header to a message.
   *
   * @param \Swift_Message $message
   *   The message which the id header is to be added to.
   * @param string $key
   *   The header key.
   * @param string $value
   *   The header value.
   */
  public static function swiftmailer_add_id_header(Swift_Message $message, $key, $value) {

    // Remove any already existing header identified by the provided key.
    static::swiftmailer_remove_header($message, $key);

    // Add the header.
    $message->getHeaders()->addIdHeader($key, $value);
  }

  /**
   * Checks whether a header is a path header.
   *
   * It is difficult to distinguish id, mailbox and path headers from each other
   * as they all may very well contain the exact same value. This public static function simply
   * checks whether the header key equals to 'Return-Path' to determine if the
   * header is a path header.
   *
   * @see http://swift_mailer.org/docs/header-path
   *
   * @param string $key
   *   The header key.
   * @param string $value
   *   The header value.
   *
   * @return bool
   *   TRUE if the provided header is a path header, and otherwise FALSE.
   */
  public static function swiftmailer_is_path_header($key, $value) {
    if ($key == 'Return-Path' && \Drupal::service('email.validator')->isValid($value)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Adds a path header to a message.
   *
   * @param \Swift_Message $message
   *   The message which the path header is to be added to.
   *
   * @param string $key
   *   The header key.
   * @param string $value
   *   The header value.
   */
  public static function swiftmailer_add_path_header(Swift_Message $message, $key, $value) {

    // Remove any already existing header identified by the provided key.
    static::swiftmailer_remove_header($message, $key);

    // Add the header.
    $message->getHeaders()->addPathHeader($key, $value);
  }

  /**
   * Removes a header from a message.
   *
   * @param \Swift_Message $message
   *   The message which the header is to be removed from.
   * @param string $key
   *   The header key.
   */
  public static function swiftmailer_remove_header(Swift_Message $message, $key) {

    // Get message headers.
    $headers = $message->getHeaders();

    // Remove the header if it already exists.
    $headers->removeAll($key);

  }

  /**
   * Converts a string holding one or more mailboxes to an array.
   *
   * @param $value
   *   A string holding one or more mailboxes.
   *
   * @return array
   *   this return array
   */
  public static function swiftmailer_parse_mailboxes($value) {
    $validator = \Drupal::service('email.validator');

    // Remove line breaks, which are legitimate within a mime header.
    // A line break within the header does not mean
    // a line break within the header value.
    // See RFC 2047, the related RFCs.
    // @see https://www.drupal.org/project/drupal/issues/84883.
    $value = str_replace(["\n", "\r"], '', $value);

    // Split mailboxes by ',' (comma) and ';' (semicolon).
    $mailboxes_raw = [];
    preg_match_all("/((?:^|\s){0,}(?:(?:\".*?\"){0,1}.*?)(?:$|,|;))/", $value, $mailboxes_raw);

    // Define an array which will keep track of mailboxes.
    $mailboxes = [];

    // Iterate through each of the raw mailboxes and process them.
    foreach ($mailboxes_raw[0] as $mailbox_raw) {
      if (empty($mailbox_raw)) {
        continue;
      }

      // Remove leading and trailing whitespace.
      $mailbox_raw = trim($mailbox_raw);

      if (preg_match('/^.*<.*>.*$/', $mailbox_raw)) {
        $mailbox_components = explode('<', $mailbox_raw);
        $mailbox_name = trim(preg_replace("/\"/", "", $mailbox_components[0]));
        $mailbox_address = preg_replace('/>.*/', '', $mailbox_components[1]);
        if ($validator->isValid($mailbox_address)) {
          $mailboxes[$mailbox_address] = $mailbox_name;
        }
      }
      else {
        $mailbox_address = preg_replace("/(,|;)/", "", $mailbox_raw);
        if ($validator->isValid($mailbox_address)) {
          $mailboxes[] = $mailbox_address;
        }
      }
    }

    return $mailboxes;
  }

  /**
   * Filters out unwanted elements from a message.
   *
   * @param \Swift_Message $message
   *   The message which unwanted elements is to be filtered out from.
   */
  public static function swiftmailer_filter_message(Swift_Message $message) {
    $headers = $message->getHeaders();

    $senders = $headers->get('From')->getAddresses();
    if (!empty($senders)) {
      for ($i = 0; $i < count($senders); $i++) {
        if (!\Drupal::service('email.validator')->isValid($senders[$i])) {
          $headers->remove('From', $i);
          \Drupal::logger('swiftmailer')->warning('The invalid "From" e-mail address "@mail" was skipped.', ['@mail' => $senders[$i]]);
        }
      }
    }

    $recipients = $headers->get('To')->getAddresses();
    if (!empty($recipients)) {
      for ($i = 0; $i < count($recipients); $i++) {
        if (!\Drupal::service('email.validator')->isValid($recipients[$i])) {
          $headers->remove('To', $i);
          \Drupal::logger('swiftmailer')->warning('The invalid "To" e-mail address "@mail" was skipped.', ['@mail' => $recipients[$i]]);
        }
      }
    }
  }

}
