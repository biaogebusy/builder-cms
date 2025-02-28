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

    foreach ($elements as $delta => $element) {
      /** @var \Drupal\Core\Url $url */
      $url = $element['#url'];
      $data = [
        'url' => $url->toString(),
        'text' => $element['#title'],
      ];
      $elements[$delta] = [
        '#type' => 'data',
        '#data' => SerializedData::create($data),
      ];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [];
  }

}
