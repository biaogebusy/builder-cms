<?php

namespace Drupal\entity_print\Event;

use Drupal\entity_print\Plugin\PrintEngineInterface;

/**
 * The PreSendPrintEvent class.
 */
class PreSendPrintEvent extends PrintEventBase {

  /**
   * An array of entities.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $entities;

  /**
   * PreSendPrintEvent constructor.
   *
   * @param \Drupal\entity_print\Plugin\PrintEngineInterface $print_engine
   *   The Print Engine.
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   The entity to print.
   */
  public function __construct(PrintEngineInterface $print_engine, array $entities) {
    parent::__construct($print_engine);
    $this->entities = $entities;
  }

  /**
   * Gets the entities that is being printed to Print.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The content entities.
   */
  public function getEntities() {
    return $this->entities;
  }

}
