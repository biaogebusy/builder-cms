<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Entity reference helper interface methods.
 */
interface EntityReferenceHelperInterface {

  /**
   * Denotes the relationship which is not entity reference.
   */
  const RELATIONSHIP_NOT_ENTITY_REFERENCE = -1;

  /**
   * Denotes the entity reference relationship which is not handleable.
   */
  const RELATIONSHIP_NOT_HANDLEABLE = 0;

  /**
   * Denotes the entity reference relationship which is handleable.
   */
  const RELATIONSHIP_HANDLEABLE = 1;

  /**
   * Check if a relationship is handleable.
   *
   * Filter on fields not targeting config entities or users.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field item list.
   *
   * @return int
   *   One of class constants which describe this relationship field.
   */
  public function relationshipHandleable(FieldItemListInterface $field);

}
