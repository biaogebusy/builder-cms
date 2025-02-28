<?php

namespace Drupal\range\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'range_decimal' formatter.
 *
 * The 'Default' formatter is different for integer fields on the one hand, and
 * for decimal and float fields on the other hand, in order to be able to use
 * different settings.
 *
 * @FieldFormatter(
 *   id = "range_decimal",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "range_decimal",
 *     "range_float"
 *   }
 * )
 */
class RangeDecimalFormatter extends RangeIntegerFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'decimal_separator' => '.',
      'scale' => 2,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['decimal_separator'] = [
      '#type' => 'select',
      '#title' => $this->t('Decimal marker'),
      '#options' => ['.' => $this->t('Decimal point'), ',' => $this->t('Comma')],
      '#default_value' => $this->getSetting('decimal_separator'),
      '#weight' => 10,
    ];
    $range = range(0, 10);
    $elements['scale'] = [
      '#type' => 'select',
      '#title' => $this->t('Scale', [], ['decimal places']),
      '#options' => array_combine($range, $range),
      '#default_value' => $this->getSetting('scale'),
      '#description' => $this->t('The number of digits to the right of the decimal.'),
      '#weight' => 15,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function formatNumber($number) {
    return number_format($number, $this->getSetting('scale'), $this->getSetting('decimal_separator'), $this->getSetting('thousand_separator'));
  }

}
