<?php

namespace Drupal\rest_views\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceIdFormatter;
use Drupal\rest_views\SerializedData;

/**
 * Plugin implementation of the 'entity reference ID' formatter.
 *
 * This duplicates the core field formatter, but makes it available for core
 * subtypes of the entity_reference fields, which are image, file, feeds_item
 * and entity_reference_revisions.
 *
 * @FieldFormatter(
 *   id = "entity_reference_entity_id_export",
 *   label = @Translation("Export Entity ID"),
 *   description = @Translation("Export ID of the referenced entity (integer or string)."),
 *   field_types = {
 *     "entity_reference",
 *     "file",
 *     "image",
 *     "entity_reference_revisions",
 *     "feeds_item"
 *   }
 * )
 */
class EntityReferenceIdExportFormatter extends EntityReferenceIdFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);

    // Transform the plaintext elements into serialized data.
    foreach ($elements as $delta => $element) {
      // Cast the ID to an integer if it is a string containing only digits.
      $id = ctype_digit($element['#plain_text']) ? (int) $element['#plain_text'] : $element['#plain_text'];
      $elements[$delta]['#type'] = 'data';
      $elements[$delta]['#data'] = SerializedData::create($id);
      unset($elements[$delta]['#plain_text']);
    }
    return $elements;
  }

}
