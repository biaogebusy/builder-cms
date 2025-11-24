<?php

namespace Drupal\rest_views\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;
use Drupal\rest_views\SerializedData;

/**
 * Export a link.
 *
 * @FieldFormatter(
 *   id = "link_export",
 *   label = @Translation("Export link"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class LinkExportFormatter extends LinkFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);
    $exportText = $this->getSetting('export_text');

    foreach ($elements as $delta => $element) {
      /** @var \Drupal\Core\Url $url */
      $url = $element['#url'];

      if ($exportText) {
        $data = [
          'url' => $url->toString(),
          'text' => $element['#title'],
        ];
        $elements[$delta] = [
          '#type' => 'data',
          '#data' => SerializedData::create($data),
        ];
      }
      else {
        $elements[$delta] = ['#markup' => $url->toString()];
      }
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $settings = [
      'export_text' => FALSE,
    ];
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $formState) {
    $elements = [];

    $title = $this->getFieldSetting('title');

    if ($title != DRUPAL_DISABLED) {
      $elements['export_text'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Export link text'),
        '#default_value' => $this->getSetting('export_text'),
      ];
    }
    else {
      $elements['export_text'] = ['#type' => 'value', '#value' => FALSE];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    if ($this->getSetting('export_text')) {
      $summary[] = $this->t('<em>Text</em> field is exported.');
    }

    return $summary;
  }

}
