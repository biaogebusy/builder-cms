<?php

namespace Drupal\mailsystem\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Maps D7 mail system settings to D9 settings.
 *
 * @MigrateProcessPlugin(
 *   id = "mailsystem_modules",
 *   handle_multiples = true
 * )
 */
class MailSystemModules extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // The "default-system" key-value pair is not for a module-specific
    // migration, but for the default. This is already handled elsewhere in the
    // migration process pipeline ("defaults/sender" and "defaults/formatter").
    unset($value['default-system']);
    foreach ($value as $key => $data) {
      // When there is no mail key, unset this value.
      if (!str_contains($key, '_')) {
        unset($value[$key]);
        continue;
      }
      [$module, $index] = explode("_", $key);
      if (\Drupal::moduleHandler()->moduleExists($module)) {
        if ($data == 'DefaultMailSystem') {
          $data = 'php_mail';
        }
        else {
          $data = 'test_mail_collector';
        }
        $value[$module] = [];
        $value[$module][$index] = [
          'formatter' => $data,
          'sender' => $data,
        ];
      }
      unset($value[$key]);
    }

    return $value;
  }

}
