<?php

namespace Drupal\range\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'range_integer_sprintf' formatter.
 *
 * The 'Formatted string' formatter is different for integer fields, and for
 * decimal and float fields in order to be able to use different settings.
 *
 * @FieldFormatter(
 *   id = "range_integer_sprintf",
 *   label = @Translation("Formatted string"),
 *   field_types = {
 *     "range_integer"
 *   },
 *   weight = 5
 * )
 */
class RangeIntegerSprintfFormatter extends RangeFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'format_string' => '%d',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['format_string'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Format'),
      '#description' => $this->t('See <a href=":url" target="_blank">PHP documentation</a> for a description of the format. <strong>Due to PHP limitations, a thousand separator cannot be used.</strong>', [':url' => 'http://php.net/manual/en/function.sprintf.php']),
      '#default_value' => $this->getSetting('format_string'),
      '#weight' => 10,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function formatNumber($number) {
    return sprintf(FieldFilteredMarkup::create($this->getSetting('format_string')), $number);
  }

}
