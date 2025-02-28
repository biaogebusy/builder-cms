<?php

namespace Drupal\rest_views\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;
use Drupal\rest_views\SerializedData;

/**
 * Plugin implementation of the 'file_export' formatter.
 *
 * @FieldFormatter(
 *   id = "file_export",
 *   label = @Translation("Export"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class FileExportFormatter extends FileFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $description = $this->fieldDefinition->getSetting('description_field');

    $elements = [];

    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items */
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      /** @var \Drupal\file\FileInterface $entity */
      $data = ['url' => file_create_url($entity->getFileUri())];
      if ($description && !empty($entity->_referringItem)) {
        $data['description'] = $entity->_referringItem->description;
      }

      $elements[$delta] = [
        '#type' => 'data',
        '#data' => SerializedData::create($data),
      ];
    }

    return $elements;
  }

}
