<?php

namespace Drupal\ai_automators\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * A rule can dispatch this event to inform about verbose information.
 */
class VerboseInformationEvent extends Event {

  // The event name.
  const EVENT_NAME = 'ai_automator.verbose_information';

  /**
   * An array of verbose information.
   *
   * @var array
   */
  protected $values = [];

  /**
   * The entity to process.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  protected $fieldDefinition;

  /**
   * The automator config.
   *
   * @var array
   */
  protected $automatorConfig;

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to process.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param array $automatorConfig
   *   The automator config.
   */
  public function __construct(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $this->entity = $entity;
    $this->fieldDefinition = $fieldDefinition;
    $this->automatorConfig = $automatorConfig;
  }

  /**
   * Get the verbose info.
   *
   * @return array
   *   The verbose info.
   */
  public function getVerboseInfo() {
    return $this->values;
  }

  /**
   * Get the entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Get the field definition.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   The field definition.
   */
  public function getFieldDefinition() {
    return $this->fieldDefinition;
  }

  /**
   * Get the automator config.
   *
   * @return array
   *   The automator config.
   */
  public function getAutomatorConfig() {
    return $this->automatorConfig;
  }

  /**
   * Set the new verbose info.
   *
   * @param array $values
   *   The new verbose info.
   */
  public function setVerboseInfo(array $values) {
    $this->values = $values;
  }

}
