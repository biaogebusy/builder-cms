<?php

namespace Drupal\ai_automators\PluginInterfaces;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Interface for automator modifiers.
 */
interface AiAutomatorDirectProcessInterface extends AiAutomatorFieldProcessInterface {

  /**
   * If the automator should process the field directly.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to process.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param array $automatorConfig
   *   The configuration for the automator.
   *
   * @return bool
   *   TRUE if the automator should process the field directly, FALSE otherwise.
   */
  public function shouldProcessDirectly(EntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig): bool;

}
