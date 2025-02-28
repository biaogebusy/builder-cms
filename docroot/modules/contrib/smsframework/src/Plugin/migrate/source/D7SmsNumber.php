<?php

declare(strict_types = 1);

namespace Drupal\sms\Plugin\migrate\source;

use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Source plugin for D7 sms_user phone number.
 *
 * @MigrateSource(
 *   id = "d7_sms_number",
 *   source_module = "sms"
 * )
 */
class D7SmsNumber extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'uid' => $this->t('User ID'),
      'number' => $this->t('Phone number'),
      'status' => $this->t('Verification Status'),
      'code' => $this->t('Verification code'),
      'gateway' => $this->t('Verification gateway'),
      'sleep_enabled' => $this->t('Sleep enabled'),
      'sleep_start_time' => $this->t('Sleep start time'),
      'sleep_end_time' => $this->t('Sleep end time'),
      'sms_user_opt_out' => $this->t('Opt out of SMS'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'uid' => [
        'type' => 'integer',
        'alias' => 'su',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('sms_user', 'su')->fields('su', array_keys($this->fields()));
  }

}
