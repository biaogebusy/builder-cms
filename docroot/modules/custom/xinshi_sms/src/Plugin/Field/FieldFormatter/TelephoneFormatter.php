<?php

namespace Drupal\xinshi_sms\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\text\Plugin\Field\FieldFormatter\TextDefaultFormatter;

/**
 * Implementation of the Telephone formatter.
 *
 * @FieldFormatter(
 *   id = "xinshi_telephone",
 *   label = @Translation("Telephone"),
 *   field_types = {
 *     "telephone"
 *   }
 * )
 */
class TelephoneFormatter extends TextDefaultFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'display_all' => '1',
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['display_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display all number'),
      '#default_value' => $this->getSetting('display_all'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $value = $item->value;
      if ($value && empty($this->getSetting('display_all'))) {
        $value = substr_replace($value, '****', 3, 4);
      }
      $elements[$delta] = [
        '#type' => 'processed_text',
        '#text' => $value,
        '#format' => $item->format,
        '#langcode' => $item->getLangcode(),
      ];
    }
    return $elements;
  }
}
