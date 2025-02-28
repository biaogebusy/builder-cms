<?php

namespace Drupal\entity_print\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * An event to alter the filenames array.
 */
class FilenameAlterEvent extends Event {

  /**
   * The Filenames array.
   *
   * @var array
   */
  protected $filenames;

  /**
   * An array of entities we're rendering.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $entities;

  /**
   * FilenameAlterEvent constructor.
   *
   * @param array $filenames
   *   The Filenames.
   * @param array $entities
   *   An array of entities we're rendering.
   */
  public function __construct(array $filenames, array $entities) {
    $this->filenames = $filenames;
    $this->entities = $entities;
  }

  /**
   * Gets the altered Filenames.
   *
   * @return array
   *   The Filenames Array.
   */
  public function &getFilenames() {
    return $this->filenames;
  }

  /**
   * Sets the altered Filenames.
   */
  public function setFilenames(array $filename) {
    $this->filenames = $filename;
  }

  /**
   * Gets the entities being rendered.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entities.
   */
  public function getEntities(): array {
    return $this->entities;
  }

}
