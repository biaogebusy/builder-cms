<?php

namespace Drupal\commerce_tax;

/**
 * Provides taxable types.
 */
final class TaxableType {

  const PHYSICAL_GOODS = 'physical_goods';
  const DIGITAL_GOODS = 'digital_goods';
  const SERVICES = 'services';
  const EVENTS = 'events';
  const NON_TAXABLE = 'non_taxable';

  /**
   * Gets the labels.
   *
   * @return array
   *   An array of labels keyed by taxable type.
   */
  public static function getLabels() {
    return [
      self::PHYSICAL_GOODS => t('Physical goods'),
      self::DIGITAL_GOODS => t('Digital goods'),
      self::SERVICES => t('Services'),
      self::EVENTS => t('Events'),
      self::NON_TAXABLE => t('Non-taxable'),
    ];
  }

  /**
   * Gets the default value.
   *
   * @return string
   *   The default value.
   */
  public static function getDefault() {
    return self::PHYSICAL_GOODS;
  }

}
