<?php

namespace Drupal\rest_views\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\rest_views\SerializedData;

/**
 * Export a list.
 *
 * @FieldFormatter(
 *   id = "list_export",
 *   label = @Translation("Export list"),
 *   field_types = {
 *     "list_integer",
 *     "list_float",
 *     "list_string",
 *   }
 * )
 */
class ListExportFormatter extends FormatterBase {
  const VALUE_LABEL = 'value_label';
  const KEY_VALUE = 'key_value';

  /**
   * Provide export option array.
   *
   * @return array
   *   Returns array of translated options.
   */
  public function getExportOptions() {
    return [
      self::VALUE_LABEL => $this->t('Export label and value separately'),
      self::KEY_VALUE   => $this->t('Export as key/value'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $exportFormat = $this->getSetting('export_format');

    // Only collect allowed options if there are actually items to display.
    if ($items->count()) {
      $provider = $items->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getOptionsProvider('value', $items->getEntity());
      // Flatten the possible options, to support opt groups.
      $options = OptGroup::flattenOptions($provider->getPossibleOptions());

      foreach ($items as $delta => $item) {
        $value = $item->value;
        $label = $options[$value] ?? "";
        if ($exportFormat == self::VALUE_LABEL) {
          $data = [
            "value" => $value,
            "label" => $label,
          ];
        }
        else {
          $data = [$value => $label];
        }
        $elements[$delta] = [
          '#type' => 'data',
          '#data' => SerializedData::create($data),
        ];
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return ['export_format' => self::VALUE_LABEL] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $formState) {
    $elements = parent::settingsForm($form, $formState);
    $elements['export_format'] = [
      '#type'          => 'radios',
      '#title'         => $this->t('Export format'),
      '#default_value' => $this->getSetting('export_format'),
      '#options'       => $this->getExportOptions(),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $exportFormat = $this->getSetting('export_format');
    $exportOptions = $this->getExportOptions();
    $summary[] = $exportOptions[$exportFormat];
    return $summary;
  }

}
