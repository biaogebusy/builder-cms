<?php

namespace Drupal\state_machine;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Defines the interface for workflow_group plugin managers.
 */
interface WorkflowGroupManagerInterface extends PluginManagerInterface {

  /**
   * Gets the definitions filtered by entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return array
   *   The definitions.
   */
  public function getDefinitionsByEntityType($entity_type_id = NULL);

}
