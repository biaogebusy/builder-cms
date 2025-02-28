<?php

declare(strict_types = 1);

namespace Drupal\sms\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Creates phone number settings for new D8 site based on D6/D7 settings.
 *
 * @MigrateProcessPlugin(
 *   id = "phone_number_settings"
 * )
 */
class PhoneNumberSettings extends ProcessPluginBase {

  const DEFAULT_D7_VERIFICATION_MESSAGE = '[site:name] confirmation code: ';
  const DEFAULT_D6_VERIFICATION_MESSAGE = '[site-name] confirmation code: [confirm-code]';
  const DEFAULT_VERIFICATION_MESSAGE = "Your verification code is '[sms-message:verification-code]'.\nGo to [sms:verification-url] to verify your phone number.\n- [site:name]";

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($row->getSourceProperty('id') === 'sms_user_confirmation_message') {
      // If still using the D6/D7 default message, swap for the new D8 default.
      if (empty($value) || $value == static::DEFAULT_D6_VERIFICATION_MESSAGE
        || $value == static::DEFAULT_D7_VERIFICATION_MESSAGE) {
        $value = static::DEFAULT_VERIFICATION_MESSAGE;
      }
      else {
        // Replace both D6 and D7 message token formats.
        $value = str_replace('[site-name]', '[site:name]', $value);
        $value = str_replace('[confirm-code]', '[sms-message:verification-code]', $value);
        $value = str_replace('[confirm:code]', '[sms-message:verification-code]', $value);
      }
    }
    return $value;
  }

}
