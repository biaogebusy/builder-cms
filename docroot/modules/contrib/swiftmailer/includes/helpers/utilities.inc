<?php

/**
 * @file
 * This file contains general utility functions.
 */

/**
 * Returns a list of available encryption options.
 *
 * @return array
 *   A list of available encryption options.
 */
function swiftmailer_get_encryption_options() {

  // Define a variable to hold the various encryption options.
  $encryption_options[0] = t('No Encryption');

  // Get available encryption options on this system.
  $system_transports = stream_get_transports();

  // Validate wether this system supports SSL.
  if (in_array('ssl', $system_transports)) {
    $encryption_options['ssl'] = t('SSL');
  }

  // Validate wether this system supports TLS.
  if (in_array('tls', $system_transports)) {
    $encryption_options['tls'] = t('TLS');
  }

  // Return available options.
  return $encryption_options;

}

/**
 * Returns available character sets.
 *
 * @return array
 *   A list of available character sets.
 */
function swiftmailer_get_character_set_options() {

  $character_set_options = [
    'UCS-4' => 'UCS-4',
    'UCS-4BE' => 'UCS-4BE',
    'UCS-4LE' => 'UCS-4LE',
    'UCS-2' => 'UCS-2',
    'UCS-2BE' => 'UCS-2BE',
    'UCS-2LE' => 'UCS-2LE',
    'UTF-32' => 'UTF-32',
    'UTF-32BE' => 'UTF-32BE',
    'UTF-32LE' => 'UTF-32LE',
    'UTF-16' => 'UTF-16',
    'UTF-16BE' => 'UTF-16BE',
    'UTF-16LE' => 'UTF-16LE',
    'UTF-7' => 'UTF-7',
    'UTF7-IMAP' => 'UTF7-IMAP',
    'UTF-8' => 'UTF-8',
    'ASCII' => 'ASCII',
    'EUC-JP' => 'EUC-JP',
    'SJIS' => 'SJIS',
    'eucJP-win' => 'eucJP-win',
    'SJIS-win' => 'SJIS-win',
    'ISO-2022-JP' => 'ISO-2022-JP',
    'JIS' => 'JIS',
    'ISO-8859-1' => 'ISO-8859-1',
    'ISO-8859-2' => 'ISO-8859-2',
    'ISO-8859-3' => 'ISO-8859-3',
    'ISO-8859-4' => 'ISO-8859-4',
    'ISO-8859-5' => 'ISO-8859-5',
    'ISO-8859-6' => 'ISO-8859-6',
    'ISO-8859-7' => 'ISO-8859-7',
    'ISO-8859-8' => 'ISO-8859-8',
    'ISO-8859-9' => 'ISO-8859-9',
    'ISO-8859-10' => 'ISO-8859-10',
    'ISO-8859-13' => 'ISO-8859-13',
    'ISO-8859-14' => 'ISO-8859-14',
    'ISO-8859-15' => 'ISO-8859-15',
    'byte2be' => 'byte2be',
    'byte2le' => 'byte2le',
    'byte4be' => 'byte4be',
    'byte4le' => 'byte4le',
    'BASE64' => 'BASE64',
    'HTML-ENTITIES' => 'HTML-ENTITIES',
    '7bit' => '7bit',
    '8bit' => '8bit',
    'EUC-CN' => 'EUC-CN',
    'CP936' => 'CP936',
    'HZ' => 'HZ',
    'EUC-TW' => 'EUC-TW',
    'CP950' => 'CP950',
    'BIG-5' => 'BIG-5',
    'EUC-KR' => 'EUC-KR',
    'UHC (CP949)' => 'UHC (CP949)',
    'ISO-2022-KR' => 'ISO-2022-KR',
    'Windows-1251 (CP1251)' => 'Windows-1251 (CP1251)',
    'Windows-1252 (CP1252)' => 'Windows-1252 (CP1252)',
    'CP866 (IBM866)' => 'CP866 (IBM866)',
    'KOI8-R' => 'KOI8-R',
  ];

  return $character_set_options;
}

/**
 * Returns a list of supressable e-mail headers.
 *
 * The returned e-mail headers could be provided by Drupal, but should be
 * ignored in order to make Swift Mailer work as smooth as possible.
 *
 * @return array
 *   A list of e-mail headers which could be provided by Drupal, but which
 *   should be ignored.
 */
function swiftmailer_get_supressable_headers() {

  return [
    'Content-Transfer-Encoding',
  ];

}

/**
 * Validates whether a message is multipart or not.
 *
 * @param array $message
 *   The message which is to be validatet.
 *
 * @return bool
 *   A boolean indicating whether the message is multipart or not.
 */
function swiftmailer_is_multipart(&$message) {

  $parts = 0;

  if (!empty($message['body'])) {
    $parts++;
  }

  if (!empty($message['plain'])) {
    $parts++;
  }

  if (!empty($message['params']['files'])) {
    $parts++;
  }

  if (!empty($message['params']['images'])) {
    $parts++;
  }

  return ($parts > 1) ? TRUE : FALSE;
}
