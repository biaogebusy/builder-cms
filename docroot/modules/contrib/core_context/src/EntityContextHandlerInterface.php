<?php

namespace Drupal\core_context;

use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines an interface for entity handlers that expose contexts.
 */
interface EntityContextHandlerInterface extends EntityHandlerInterface {

  /**
   * Returns all contexts attached to an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return \Drupal\Component\Plugin\Context\ContextInterface[]
   */
  public function getContexts(EntityInterface $entity);

}
