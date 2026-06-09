<?php

namespace Drupal\ai_translate;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines interface of the text extractor service.
 */
interface TextExtractorInterface {

  /**
   * Extract translatable text metadata from an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity to process.
   * @param array $parents
   *   Array of parents for processing nested entities.
   *
   * @return array
   *   Text metadata to pass to translation service.
   */
  public function extractTextMetadata(ContentEntityInterface $entity, array $parents = []) : array;

  /**
   * Insert text metadata, including translations.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity being translated.
   * @param array $metadata
   *   Text metadata.
   */
  public function insertTextMetadata(ContentEntityInterface $entity, array $metadata) : void;

}
