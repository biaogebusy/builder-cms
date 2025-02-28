<?php

namespace Drupal\range\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\range\RangeItemInterface;

/**
 * Parent plugin for decimal and integer range formatters.
 */
abstract class RangeFormatterBase extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'range_separator' => '-',
      'range_combine' => TRUE,
      'field_prefix_suffix' => FALSE,
      'from_prefix_suffix' => FALSE,
      'to_prefix_suffix' => FALSE,
      'combined_prefix_suffix' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['range_separator'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Range separator'),
      '#default_value' => $this->getSetting('range_separator'),
      '#weight' => 0,
    ];
    $elements['range_combine'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Combine equivalent values'),
      '#description' => $this->t('If the FROM and TO values are equal, combine the display into a single value.'),
      '#default_value' => $this->getSetting('range_combine'),
      '#weight' => 50,
    ];
    $elements['field_prefix_suffix'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display <em>FIELD value</em> prefix and suffix'),
      '#default_value' => $this->getSetting('field_prefix_suffix'),
      '#weight' => 55,
    ];
    $elements['from_prefix_suffix'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display <em>FROM value</em> prefix and suffix'),
      '#default_value' => $this->getSetting('from_prefix_suffix'),
      '#weight' => 60,
    ];
    $elements['to_prefix_suffix'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display <em>TO value</em> prefix and suffix'),
      '#default_value' => $this->getSetting('to_prefix_suffix'),
      '#weight' => 65,
    ];
    $elements['combined_prefix_suffix'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display <em>COMBINED value</em> prefix and suffix'),
      '#default_value' => $this->getSetting('combined_prefix_suffix'),
      '#weight' => 70,
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][range_combine]"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $from_value = $this->formatNumber(1234.1234567890);
    $to_value = $this->formatNumber(4321.0987654321);

    $summary[] = [
      '#theme' => 'range_formatter_range_separate',
      '#from' => $from_value,
      '#range_separator' => $this->getSetting('range_separator'),
      '#to' => $to_value,
    ];
    if ($this->getSetting('range_combine')) {
      $summary[] = $this->t('Equivalent values will be combined into a single value.');
    }
    if ($this->getSetting('field_prefix_suffix')) {
      $summary[] = $this->t('Display with <em>FIELD value</em> prefix and suffix.');
    }
    if ($this->getSetting('from_prefix_suffix')) {
      $summary[] = $this->t('Display with <em>FROM value</em> prefix and suffix.');
    }
    if ($this->getSetting('to_prefix_suffix')) {
      $summary[] = $this->t('Display with <em>TO value</em> prefix and suffix.');
    }
    if ($this->getSetting('range_combine') && $this->getSetting('combined_prefix_suffix')) {
      $summary[] = $this->t('Display with <em>COMBINED value</em> prefix and suffix.');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $from_value = $this->formatNumber($item->from);
      $to_value = $this->formatNumber($item->to);

      // Combine values if they are equal.
      if ($this->getSetting('range_combine') && $from_value === $to_value) {
        $output = $this->viewElementCombined($item, $from_value);
      }
      else {
        $output = $this->viewElementSeparate($item, $from_value, $to_value);
      }

      if ($this->getSetting('field_prefix_suffix')) {
        $output['#field_prefix'] = FieldFilteredMarkup::create($this->getFieldSetting('field')['prefix']);
        $output['#field_suffix'] = FieldFilteredMarkup::create($this->getFieldSetting('field')['suffix']);
      }

      $elements[$delta] = $output;
    }

    return $elements;
  }

  /**
   * Helper method. Returns the combined value renderable array.
   *
   * FROM and TO might have different prefixes/suffixes.
   * Code below decides which one to use, based on the following:
   *   1. If COMBINED is disabled and both FROM/TO are disabled - show naked
   *      value.
   *   2. If COMBINED is enabled - show it.
   *   3. If COMBINED is disabled and either FROM or TO are enabled - show
   *      prefix/suffix of the enabled one.
   *   4. If COMBINED is disabled and both FROM/TO are enabled, show prefix
   *      from FROM and suffix from TO.
   *
   * @param \Drupal\range\RangeItemInterface $item
   *   Range field item.
   * @param string $value
   *   Field combined value.
   *
   * @return array
   *   Field value renderable array.
   */
  protected function viewElementCombined(RangeItemInterface $item, $value) {
    $from_prefix_suffix = !empty($this->getSetting('from_prefix_suffix'));
    $to_prefix_suffix = !empty($this->getSetting('to_prefix_suffix'));
    $combined_prefix_suffix = !empty($this->getSetting('combined_prefix_suffix'));

    // Option #1: COMBINED is disabled and both FROM/TO are disabled.
    $output = [
      '#theme' => 'range_formatter_range_combined',
      '#item' => $item,
      '#value' => $value,
    ];
    // Option #2: COMBINED is enabled.
    if ($combined_prefix_suffix) {
      $output['#value_prefix'] = FieldFilteredMarkup::create($this->getFieldSetting('combined')['prefix']);
      $output['#value_suffix'] = FieldFilteredMarkup::create($this->getFieldSetting('combined')['suffix']);
    }
    // Option #3a: COMBINED is disabled and FROM is enabled.
    elseif ($from_prefix_suffix && !$to_prefix_suffix) {
      $output['#value_prefix'] = FieldFilteredMarkup::create($this->getFieldSetting('from')['prefix']);
      $output['#value_suffix'] = FieldFilteredMarkup::create($this->getFieldSetting('from')['suffix']);
    }
    // Option #3b: COMBINED is disabled and TO is enabled.
    elseif (!$from_prefix_suffix && $to_prefix_suffix) {
      $output['#value_prefix'] = FieldFilteredMarkup::create($this->getFieldSetting('to')['prefix']);
      $output['#value_suffix'] = FieldFilteredMarkup::create($this->getFieldSetting('to')['suffix']);
    }
    // Option #4: COMBINED is disabled and both FROM/TO are enabled.
    elseif ($from_prefix_suffix && $to_prefix_suffix) {
      $output['#value_prefix'] = FieldFilteredMarkup::create($this->getFieldSetting('from')['prefix']);
      $output['#value_suffix'] = FieldFilteredMarkup::create($this->getFieldSetting('to')['suffix']);
    }

    return $output;
  }

  /**
   * Helper method. Returns the separate values renderable array.
   *
   * @param \Drupal\range\RangeItemInterface $item
   *   Range field item.
   * @param string $from_value
   *   Field FROM value.
   * @param string $to_value
   *   Field TO value.
   *
   * @return array
   *   Field value renderable array.
   */
  protected function viewElementSeparate(RangeItemInterface $item, $from_value, $to_value) {
    $output = [
      '#theme' => 'range_formatter_range_separate',
      '#item' => $item,
      '#from' => $from_value,
      '#range_separator' => $this->getSetting('range_separator'),
      '#to' => $to_value,
    ];

    if ($this->getSetting('from_prefix_suffix')) {
      $output['#from_prefix'] = FieldFilteredMarkup::create($this->getFieldSetting('from')['prefix']);
      $output['#from_suffix'] = FieldFilteredMarkup::create($this->getFieldSetting('from')['suffix']);
    }
    if ($this->getSetting('to_prefix_suffix')) {
      $output['#to_prefix'] = FieldFilteredMarkup::create($this->getFieldSetting('to')['prefix']);
      $output['#to_suffix'] = FieldFilteredMarkup::create($this->getFieldSetting('to')['suffix']);
    }

    return $output;
  }

  /**
   * Formats a number.
   *
   * @param mixed $number
   *   The numeric value.
   *
   * @return string
   *   The formatted number.
   */
  abstract protected function formatNumber($number);

}
