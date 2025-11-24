<?php

/**
 * @file
 * Token callbacks for the sms module.
 */

declare(strict_types = 1);

use Drupal\Core\GeneratedUrl;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\sms\Message\SmsMessageInterface;

/**
 * Implements hook_token_info().
 */
function sms_token_info(): array {
  $info['types']['sms'] = [
    'name' => t('SMS'),
    'description' => t("SMS Framework tokens."),
  ];
  $info['tokens']['sms']['verification-url'] = [
    'name' => t("Verification URL"),
    'description' => t("The URL of the site verification page."),
  ];
  $info['types']['sms-message'] = [
    'name' => t('SMS Message'),
    'needs-data' => 'sms-message',
    'description' => t("Tokens for an SMS Message."),
  ];
  $info['tokens']['sms-message']['phone-number'] = [
    'name' => t('Phone number'),
    'description' => t("Recipient phone number"),
  ];
  $info['tokens']['sms-message']['message'] = [
    'name' => t('Message'),
    'description' => t("Contents of the SMS message."),
  ];
  $info['tokens']['sms-message']['verification-code'] = [
    'name' => t("Verification Code"),
    'description' => t("A verification code for a phone number."),
  ];
  return $info;
}

/**
 * Implements hook_tokens().
 */
function sms_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type == 'sms') {
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'verification-url':
          $url_options = ['absolute' => TRUE];
          $result = Url::fromRoute('sms.phone.verify', [], $url_options)->toString(TRUE);
          assert($result instanceof GeneratedUrl);
          $replacements[$original] = $result->getGeneratedUrl();
          break;
      }
    }
  }

  if ($type == 'sms-message') {
    if (!empty($data['sms-message']) && ($data['sms-message'] instanceof SmsMessageInterface)) {
      /** @var \Drupal\sms\Message\SmsMessageInterface $sms_message */
      $sms_message = $data['sms-message'];

      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'phone-number':
            $recipients = $sms_message->getRecipients();
            $replacements[$original] = reset($recipients) ?: '';
            break;

          case 'message':
            $replacements[$original] = $sms_message->getMessage();
            break;
        }
      }
    }

    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'verification-code':
          if (!empty($data['sms_verification_code'])) {
            $replacements[$original] = $data['sms_verification_code'];
          }
          break;
      }
    }
  }

  return $replacements;
}
