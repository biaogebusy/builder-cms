<?php

namespace Drupal\state_machine;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Defines the interface for workflow plugin managers.
 */
interface WorkflowManagerInterface extends PluginManagerInterface {

  /**
   * Gets the grouped workflow labels.
   *
   * @param string $entity_type_id
   *   (optional) The entity type id to filter by. If provided, only workflows
   *   that belong to groups with the specified entity type will be returned.
   *
   * @return array
   *   Keys are group labels, and values are arrays of which the keys are
   *   workflow IDs and the values are workflow labels.
   */
  public function getGroupedLabels($entity_type_id = NULL);

}
