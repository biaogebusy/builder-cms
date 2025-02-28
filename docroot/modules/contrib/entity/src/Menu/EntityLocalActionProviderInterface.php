<?php

namespace Drupal\entity\Menu;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Provides an interface for entity local action providers.
 */
interface EntityLocalActionProviderInterface {

  /**
   * Builds local actions for the given entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array[]
   *   An array of local action definitions.
   */
  public function buildLocalActions(EntityTypeInterface $entity_type);

}
