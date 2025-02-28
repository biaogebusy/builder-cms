<?php

namespace Drupal\entity_theme_engine\Normalizer;


use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

class DateItemNormalizer extends FieldItemNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = ['Drupal\datetime\Plugin\Field\FieldType\DateTimeItem'];
  
  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    $data = parent::normalize($field, $format, $context);
    if($date = $field->date) {
      $timezone = DateTimeItemInterface::STORAGE_TIMEZONE;
      $date->setTimeZone(timezone_open($timezone));
      $data['value'] = \Drupal::service('date.formatter')->format($date->getTimestamp());
    }
    return  $data;
  }

}
