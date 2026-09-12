<?php

namespace Drupal\core_context\ContextProvider;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextProviderInterface;

/**
 * Provides a base class for context providers.
 */
abstract class ContextProviderBase implements ContextProviderInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ContextProviderBase constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Extracts contexts from an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity from which to extract contexts.
   *
   * @return \Drupal\Component\Plugin\Context\ContextInterface[]
   *   The contexts extracted from the entity.
   */
  protected function getContextsFromEntity(EntityInterface $entity) {
    $entity_type = $entity->getEntityType();

    if ($entity_type->hasHandlerClass('context')) {
      return $this->entityTypeManager->getHandler($entity_type->id(), 'context')
        ->getContexts($entity);
    }
    return [];
  }

}
