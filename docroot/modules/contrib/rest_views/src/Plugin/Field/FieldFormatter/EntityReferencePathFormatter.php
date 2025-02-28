<?php

namespace Drupal\rest_views\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;
use Drupal\rest_views\SerializedData;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'entity_path' formatter.
 *
 * @FieldFormatter(
 *   id = "entity_path",
 *   label = @Translation("Entity path"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferencePathFormatter extends LinkFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      /** @var \Drupal\Core\Url $url */
      $url = $item->entity->toUrl();
      $elements[$delta] = [
        '#type' => 'data',
        '#data' => SerializedData::create($url->toString()),
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
