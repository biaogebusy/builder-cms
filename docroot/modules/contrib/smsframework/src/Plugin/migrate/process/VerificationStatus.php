<?php

declare(strict_types = 1);

namespace Drupal\sms\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Changes verification status from D6/D7 format [0, 1, 2] to D8 [true, false].
 *
 * @MigrateProcessPlugin(
 *   id = "sms_verification_status"
 * )
 */
class VerificationStatus extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return $value == 2;
  }

}
