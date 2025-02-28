<?php

namespace Drupal\comment_delete;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides interface for comment thread manager.
 */
interface CommentThreadManagerInterface {

  /**
   * Recalculate comment threads on an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The commented entity.
   */
  public function calculate(EntityInterface $entity): void;

}
