<?php

namespace Drupal\content_lock\Plugin\Action;

use Drupal\Core\Action\Plugin\Action\Derivative\EntityActionDeriverBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Provides an action deriver that finds content entity types.
 */
class BreakLockDeriver extends EntityActionDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function isApplicable(EntityTypeInterface $entity_type): bool {
    return $entity_type->entityClassImplements(ContentEntityInterface::class);
  }

}
