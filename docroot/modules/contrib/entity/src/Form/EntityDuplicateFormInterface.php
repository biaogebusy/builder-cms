<?php

namespace Drupal\entity\Form;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines an interface for entity duplicate forms.
 */
interface EntityDuplicateFormInterface extends EntityFormInterface {

  /**
   * Gets the source entity.
   *
   * This is the entity that was duplicated to populate the form entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The source entity.
   */
  public function getSourceEntity();

  /**
   * Sets the source entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   The source entity.
   *
   * @return $this
   */
  public function setSourceEntity(EntityInterface $source_entity);

}
