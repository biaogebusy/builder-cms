<?php

declare(strict_types = 1);

namespace Drupal\sms\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\sms\Direction;

/**
 * Field handler to show SMS message direction.
 *
 * @ViewsField(\Drupal\sms\Plugin\views\field\SmsMessageDirection::PLUGIN_ID)
 */
class SmsMessageDirection extends FieldPluginBase {

  public const PLUGIN_ID = 'sms_message_direction';

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    switch ($this->getValue($values)) {
      case Direction::INCOMING:
        return $this->t('Incoming');

      case Direction::OUTGOING:
        return $this->t('Outgoing');

      default:
        return $this->t('Unknown direction');
    }
  }

}
