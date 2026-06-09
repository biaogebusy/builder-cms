<?php

namespace Drupal\ai_translate;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldConfigInterface;

/**
 * Interface for classes that extract text from fields in content entities.
 */
interface FieldTextExtractorInterface {

  /**
   * The field columns to extract.
   *
   * @return string[]
   *   An array of field columns.
   */
  public function getColumns(): array;

  /**
   * Check whether this field should be extracted.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity.
   * @param \Drupal\Core\Field\FieldConfigInterface $fieldDefinition
   *   Field definition.
   *
   * @return bool
   *   TRUE to attempt to extract texts from this field.
   */
  public function shouldExtract(ContentEntityInterface $entity, FieldConfigInterface $fieldDefinition) : bool;

  /**
   * Extract text metadata from an entity field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Source entity.
   * @param string $fieldName
   *   Field name.
   *
   * @return array
   *   Array of text metadata arrays to translate.
   *   Metadata array has a special "_columns" key to specify which
   *   parts of metadata should be translated.
   *   Example text metadata:
   *   [
   *     'delta' => 0,
   *     'field_name' => 'field_faq',
   *     'question' => 'What is the capital of the United Kingdom?',
   *     'answer' => 'London',
   *     '_columns' => ['question', 'answer'],
   *   ].
   *   Default value for '_columns' is ['value'].
   */
  public function extract(ContentEntityInterface $entity, string $fieldName): array;

  /**
   * Set translation values in the field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity to update.
   * @param string $fieldName
   *   Field name.
   * @param array $textMeta
   *   Text metadata including translation.
   */
  public function setValue(ContentEntityInterface $entity, string $fieldName, array $textMeta) : void;

}
