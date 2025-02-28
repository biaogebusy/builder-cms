<?php

namespace Drupal\entity\Menu;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Provides an interface for entity local task providers.
 */
interface EntityLocalTaskProviderInterface {

  /**
   * Builds local tasks for the given entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array[]
   *   An array of local task definitions.
   */
  public function buildLocalTasks(EntityTypeInterface $entity_type);

}
