<?php

namespace Drupal\ai_automators\Traits;

/**
 * Trait to help with automator instructions.
 *
 * @package Drupal\ai_automators\Traits
 */
trait AutomatorInstructionTrait {

  /**
   * Get available automator instructions.
   *
   * @param string|null $entity_type
   *   The entity type.
   * @param string|null $bundle
   *   The bundle.
   *
   * @return array
   *   The available automator instructions.
   */
  protected function getAutomatorInstructions($entity_type = NULL, $bundle = NULL) {
    $definitions = \Drupal::entityTypeManager()->getStorage('ai_automator')->loadMultiple();
    $options = [];
    /** @var \Drupal\ai_automators\Entity\AiAutomator $definition */
    foreach ($definitions as $definition) {
      if ((empty($entity_type) || $definition->get('entity_type') == $entity_type) && (empty($bundle) || $definition->get('bundle') == $bundle)) {
        $options[$definition->id()] = $definition;
      }
    }
    return $options;
  }

}
