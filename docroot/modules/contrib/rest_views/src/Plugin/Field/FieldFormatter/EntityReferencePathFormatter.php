<?php

namespace Drupal\rest_views\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rest_views\SerializedData;

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
class EntityReferencePathFormatter extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode = NULL): array {
    $elements = [];

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {

      $absolute = $this->getSetting('absolute') ?? FALSE;
      /** @var \Drupal\Core\Url $url */
      $url = $entity->toUrl(NULL, ['absolute' => $absolute]);
      $elements[$delta] = [
        '#type' => 'data',
        '#data' => SerializedData::create($url->toString()),
        '#cache' => [
          'tags' => $entity->getCacheTags(),
        ],
      ];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $settings = parent::defaultSettings();

    $settings['absolute'] = FALSE;
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $formState): array {
    $form = parent::settingsForm($form, $formState);

    $form['absolute'] = [
      '#title' => $this->t('Output an absolute path'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('absolute'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    if ($this->getSetting('absolute')) {
      $summary[] = $this->t('Outputs an absolute path');
    }
    return $summary;
  }

}
