<?php

namespace Drupal\ai_translate\Plugin\FieldTextExtractor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_translate\Attribute\FieldTextExtractor;

/**
 * A field text extractor plugin for text fields.
 */
#[FieldTextExtractor(
  id: "text",
  label: new TranslatableMarkup('Text'),
  field_types: [
    'title',
    'text',
    'text_long',
    'string',
    'string_long',
  ],
)]
class TextFieldExtractor extends FieldExtractorBase {

  /**
   * {@inheritdoc}
   */
  public function getColumns(): array {
    return ['value'];
  }

  /**
   * {@inheritdoc}
   */
  public function setValue(ContentEntityInterface $entity, string $fieldName, array $textMeta) : void {
    $newValue = $entity->get($fieldName)->getValue();
    foreach ($textMeta as $delta => $singleValue) {
      unset($singleValue['field_name'], $singleValue['field_type']);
      // Merge the original (untranslated) value with the translated value.
      // Original value might be empty, e.g. when ReferenceFieldExtractor
      // plugin has created a translated version of an entity.
      $newValue[$delta] = isset($newValue[$delta]) ? array_merge($newValue[$delta], $singleValue) : $singleValue;
      // Trim result if the field definition has a length limit.
      $field_definition = $entity->getFieldDefinition($fieldName);
      // Check if the field definition has a max length value.
      if ($field_definition instanceof FieldDefinitionInterface) {
        $settings = $field_definition->getSettings();
        // Retrieve the max length if it exists.
        $max_length = $settings['max_length'] ?? NULL;
        if ($max_length !== NULL) {
          $newValue[$delta]['value'] = mb_strimwidth(
            $newValue[$delta]['value'], 0, $max_length, '...');
        }
      }
    }
    $entity->set($fieldName, $newValue);
  }

}
