<?php

namespace Drupal\rest_views\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\rest_views\RenderableData;
use Drupal\rest_views\SerializedData;

/**
 * A plugin that creates a serializable form of a rendered entity.
 *
 * Only usable with the Serializable Field views plugin.
 *
 * @FieldFormatter(
 *   id = "entity_reference_export",
 *   label = @Translation("Export rendered entity"),
 *   description = @Translation("Export the entity rendered by entity_view()."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceExportFormatter extends EntityReferenceEntityFormatter {

  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, $langcode = NULL): array {
    $elements = $this->viewElements($items, $langcode);

    foreach ($items as $k => $item) {
      if (empty($elements[$k]) && $item->entity) {
        $items->removeItem($k);
      }
    }

    $elements = array_values($elements);
    $output = ['#items' => $items];

    $entityKey = '#' . $this->getFieldSetting('target_type');
    $extra = $this->getSetting('extra');

    foreach ($elements as $delta => $row) {
      $output[$delta] = [];

      // Entities build their fields in a pre-render function.
      if (isset($row['#pre_render'])) {
        foreach ((array) $row['#pre_render'] as $callable) {
          $row = $callable($row);
        }
      }

      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = $row[$entityKey];

      if (!empty($extra['id'])) {
        // Cast the ID to an integer if it is a string containing only digits.
        $output[$delta]['id'] = ctype_digit($entity->id()) ? (int) $entity->id() : $entity->id();
      }
      if (!empty($extra['title'])) {
        $output[$delta]['title'] = $entity->label();
      }
      if (!empty($extra['url'])) {
        try {
          $output[$delta]['url'] = $entity->toUrl()->setAbsolute()->toString();
        }
        catch (\Exception) {
          $output[$delta]['url'] = NULL;
        }
      }
      if (!empty($extra['type'])) {
        $output[$delta]['type'] = $entity->getEntityTypeId();
      }
      if (!empty($extra['bundle'])) {
        $output[$delta]['bundle'] = $entity->bundle();
      }

      // Traverse the fields and build a serializable array.
      foreach (Element::children($row) as $name) {
        $alias = preg_replace('/^field_/', '', $name);
        if (!empty($output[$delta][$alias])) {
          continue;
        }

        $field = $row[$name];
        foreach (Element::children($field) as $index) {
          $value = $field[$index];
          if (isset($value['#type']) && $value['#type'] === 'data') {
            $value = SerializedData::create($value['#data']);
          }
          else {
            $value = RenderableData::create($value);
          }
          $output[$delta][$alias][$index] = $value;
        }

        // If the field has no multiple cardinality, unpack the array.
        if (!empty($field['#items'])) {
          /** @var \Drupal\Core\Field\FieldItemListInterface $fieldItems */
          $fieldItems = $field['#items'];
          if (!$fieldItems
            ->getFieldDefinition()
            ->getFieldStorageDefinition()
            ->isMultiple()
          ) {
            $output[$delta][$alias] = isset($output[$delta][$alias]) && is_array($output[$delta][$alias]) ? reset($output[$delta][$alias]) : NULL;
          }
        }
      }

      $output[$delta] = [
        '#type' => 'data',
        '#data' => SerializedData::create($output[$delta]),
      ];
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return ['extra' => []] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $formState): array {
    $elements = parent::settingsForm($form, $formState);
    $elements['extra'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Export metadata'),
      '#default_value' => $this->getSetting('extra'),
      '#options'       => [
        'id'      => $this->t('ID'),
        'title'   => $this->t('Title'),
        'url'     => $this->t('URL'),
        'type'    => $this->t('Entity type'),
        'bundle'  => $this->t('Entity bundle'),
        'uid'     => $this->t('Author'),
        'created' => $this->t('Creation date'),
      ],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $fields = $this->getSetting('extra');
    $labels = array_filter([
      'id'      => $this->t('ID'),
      'title'   => $this->t('Title'),
      'url'     => $this->t('URL'),
      'type'    => $this->t('Entity type'),
      'bundle'  => $this->t('Entity bundle'),
      'uid'     => $this->t('Author'),
      'created' => $this->t('Creation date'),
    ], static function ($key) use ($fields) {
      return !empty($fields[$key]);
    }, ARRAY_FILTER_USE_KEY);

    if ($labels) {
      $summary[] = $this->t('Includes %data', ['%data' => implode(', ', $labels)]);
    }
    return $summary;
  }

}
