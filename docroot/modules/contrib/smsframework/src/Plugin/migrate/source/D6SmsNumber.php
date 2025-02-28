<?php

declare(strict_types = 1);

namespace Drupal\sms\Plugin\migrate\source;

use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Source plugin for D6 sms_user phone number.
 *
 * @MigrateSource(
 *   id = "d6_sms_number",
 *   source_module = "sms"
 * )
 */
class D6SmsNumber extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'uid' => $this->t('User ID'),
      'delta' => $this->t('Delta'),
      'number' => $this->t('Phone number'),
      'status' => $this->t('Verification Status'),
      'code' => $this->t('Verification code'),
      'gateway' => $this->t('Verification gateway'),
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
      'delta' => [
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
