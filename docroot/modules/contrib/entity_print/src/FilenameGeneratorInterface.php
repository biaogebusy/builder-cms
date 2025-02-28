<?php

namespace Drupal\entity_print;

/**
 * The filename generator interface.
 */
interface FilenameGeneratorInterface {

  /**
   * The filename used when we're unable to calculate a filename.
   *
   * @var string
   */
  const DEFAULT_FILENAME = 'document';

  /**
   * Generates a filename to be used for a printed document.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   An array of entities to generate a filename for.
   * @param callable $entity_label_callback
   *   (optional) A function to generate the label for an individual entity.
   *
   * @return string
   *   The generated filename.
   */
  public function generateFilename(array $entities, callable $entity_label_callback = NULL);

}
